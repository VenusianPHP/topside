<?php

namespace Surface\Toolkits;

use Surface\Toolkits\EventLoop\ToolkitPump;
use WeakMap;
use ReflectionClass;
use RuntimeException;
use ReflectionException;
use Surface\Contracts\Toolkits\Toolkit;
use Voyager\Contracts\Core\FrameworkCore;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Voyager\Contracts\IOPools\Loop as LoopInterface;
use Surface\Contracts\Toolkits\ToolkitSession as ToolkitSessionContract;

abstract class ToolkitSession implements ToolkitSessionContract
{
    protected ?Toolkit $application = null;

    /**
     * @var list<object> posted before a loop was joined
     */
    protected array $outbox = [];

    /**
     * @var array<string, object> pending mail by key; the last post under a key wins
     */
    protected array $latest = [];

    /**
     * Flag indicating the bridge is connected
     * @var bool
     */
    protected bool $connected = false;

    /**
     * Flag indicating initialization
     * @var bool
     */
    protected bool $initialized = false;

    /**
     * The loop this session pumps for, once joined.
     */
    protected ?LoopInterface $loop = null;

    /**
     * Open windows by name: each settles after every pump.
     *
     * @var array<string, ToolkitWindow>
     */
    protected array $windows = [];

    /**
     * @var WeakMap<ToolkitSession, true>|null every connected session in the process
     */
    private static ?WeakMap $live = null;

    public function __construct(
        protected readonly FrameworkCore $app
    ) {}

    abstract protected function bootstrapToolkitLibrary(): void;

    public function connected(): bool
    {
        return $this->connected;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function connect(): static
    {
        if (! $this->connected)
        {
            $this->gateAdditionalSessions();
            $this->startSession();
            $this->connectToToolkitLibrary();

            $this->connected = true;
            self::live()[$this] = true;

            $this->addSessionToEventLoop();
        }

        return $this;
    }

    /**
     * Take the session down. The engine stays initialized: connect() brings it back.
     */
    public function disconnect(): void
    {
        if (! $this->connected) {
            return;
        }

        $this->disconnectFromToolkitLibrary();
        $this->connected = false;
        unset(self::live()[$this]);
    }

    /**
     * Deliver the pending latest mail, so a burst within one wait becomes one mail per key.
     *
     * @return void
     */
    public function flushLatest(): void
    {
        $latest = $this->latest;
        $this->latest = [];
        foreach ($latest as $mail) {
            $this->post($mail);
        }
    }

    /**
     * Hand mail from a native callback to the loop. Before joinLoop() it waits here.
     *
     * @param object $mail
     * @return void
     */
    public function post(object $mail): void
    {
        if (is_null($this->loop)) {
            $this->outbox[] = $mail;

            return;
        }

        $this->loop->post($mail);
    }

    /**
     * Block at most $budget_ns in the toolkit's own wait, dispatching what arrives.
     *
     * @return int events dispatched
     */
    public function pump(int $budget_ns): int
    {
        $dispatched = $this->application->pump($budget_ns);

        foreach ($this->windows as $window) {
            $window->settle();
            $window->settleFocus();
        }

        return $dispatched;
    }

    /**
     * Settle $window after every pump from now on. ToolkitWindow does this once it is built.
     */
    public function watchWindow(ToolkitWindow $window): void
    {
        $this->windows[$window->name()] = $window;
    }

    /**
     * Stop settling $window and drop a resize still pending for it: its target is gone.
     */
    public function unwatchWindow(ToolkitWindow $window): void
    {
        unset($this->windows[$window->name()]);
        $this->forgetLatest(self::resizedKey($window->name()));
    }

    /**
     * The latest-mail key a window's resizes collapse under.
     */
    public static function resizedKey(string $window): string
    {
        return "window.resized.{$window}";
    }

    /**
     * Hold mail under $key until flushLatest(), replacing whatever is pending there.
     */
    public function postLatest(string $key, object $mail): void
    {
        $this->latest[$key] = $mail;
    }

    /**
     * Drop the mail pending under $key, if any: its target is gone.
     */
    public function forgetLatest(string $key): void
    {
        unset($this->latest[$key]);
    }


    public function application(): Toolkit
    {
        return $this->application;
    }

    /**
     * Put this toolkit's native wait in charge of the loop's sleep and fold the loop's
     * waiter into it, so every wake of the loop ends the toolkit's sleep too.
     *
     * @throws RuntimeException Unless connected, or when the loop's waiter has no descriptor.
     */
    public function joinLoop(LoopInterface $loop): void
    {
        if (! $this->connected) {
            throw new RuntimeException('Connect the session before joining a loop.');
        }

        if ($this->loop !== $loop) {
            $descriptor = $loop->descriptor();

            if (is_null($descriptor)) {
                throw new RuntimeException('The loop waiter has no descriptor: install ext-kqueue (macOS) or ext-epoll (Linux) and set io-pools.pool_waiters.default accordingly.');
            }

            $this->wakeDescriptor($descriptor);
            $this->loop = $loop;
            $loop->resource($this->pumpName(), new ToolkitPump($this));
            $loop->crown($this->pumpName());

            foreach ($this->outbox as $mail) {
                $loop->post($mail);
            }
            $this->outbox = [];
            $this->flushLatest();
        }
    }

    /**
     * The loop resource name this session pumps under.
     */
    protected function pumpName(): string
    {
        return 'toolkit.pump.'.spl_object_id($this);
    }

    /**
     * Start the engine on first use, and never again for the life of the process.
     * @return void
     */
    protected function startSession(): void
    {
        if (! $this->initialized) {
            $this->bootstrapToolkitLibrary();
            $this->initialized = true;
        }
    }

    /**
     * Make $fd part of the toolkit's wait: readable ends the sleep.
     */
    protected function wakeDescriptor(int $fd): void
    {
        $this->application->wakeDescriptor($fd);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function addSessionToEventLoop(): void
    {
        if ($this->app->isApplicationRunning() && $this->app->isResolved('event-loop')) {
            $this->joinLoop(
                $this->app->get('event-loop')
            );
        }
    }

    /**
     * On macOS every toolkit dequeues the application's one event queue in its own pump,
     * and GTK's and SDL's key translation runs only in theirs, so a second toolkit's
     * session loses key events. One toolkit at a time there: a session of another
     * class is refused while one is connected and both answer onMacOs(). Sessions of
     * the same class (one toolkit) are not limited, and Linux gives every toolkit its
     * own display connection.
     *
     * @return void
     * @throws RuntimeException Another toolkit's session is connected on macOS.
     * @throws ReflectionException
     */
    protected function gateAdditionalSessions(): void
    {
        if(device_os_family() == 'mac')
        {
            foreach (self::live() as $session => $_)
            {
                if($session::class !== static::class && $session->connected())
                {
                    $ours = new ReflectionClass($this)->getShortName();
                    $theirs = new ReflectionClass($session)->getShortName();

                    throw new RuntimeException(
                        "{$theirs} is connected. On macOS one toolkit session pumps the application's events: disconnect it before connecting {$ours}."
                    );
                }
            }
        }
    }

    protected function connectToToolkitLibrary(): void
    {
        $this->application->connect();
    }

    protected function disconnectFromToolkitLibrary(): void
    {
        $this->application->disconnect();
    }

    protected static function live(): WeakMap
    {
        return self::$live ??= new WeakMap();
    }
}