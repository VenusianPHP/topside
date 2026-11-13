<?php

namespace Surface\Toolkits;

use WeakMap;
use ReflectionClass;
use ReflectionException;
use Voyager\Contracts\IOPools\Loop as LoopInterface;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\ToolkitSession as SessionContract;

abstract class ToolkitSession implements SessionContract
{
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
     * @var list<object> posted before a loop was joined
     */
    protected array $outbox = [];

    /**
     * @var array<string, object> pending mail by key; the last post under a key wins
     */
    protected array $latest = [];

    /**
     * @var WeakMap<ToolkitSession, true>|null every connected session in the process
     */
    private static ?WeakMap $live = null;

    public function __construct() {}

    abstract protected function initializeEngine(): void;

    abstract protected function connectToEngine(): void;

    /**
     * Withdraw the running engine from the OS: the inverse of connectToEngine().
     */
    abstract protected function disconnectEngine(): void;

    /**
     * Block at most $budget_ns in the toolkit's own wait, dispatching what arrives.
     * @return int events dispatched
     */
    abstract public function pump(int $budget_ns): int;

    /**
     * Make $fd part of the toolkit's wait: readable ends the sleep.
     */
    abstract protected function wakeDescriptor(int $fd): void;

    abstract protected function releaseWakeDescriptor(): void;

    /**
     * @throws ReflectionException
     */
    public function connect(): static
    {
        if (! $this->connected)
        {
            $this->gateAdditionalSessions();
            $this->initialize();
            $this->connectToEngine();
            $this->connected = true;
            self::live()[$this] = true;

            // In an application run, a session joins the loop when it connects.
            $app = app();
            if ($app->isApplicationRunning() && $app->isResolved('event-loop')) {
                $this->joinLoop($app->get('event-loop'));
            }
        }

        return $this;
    }

    public function connected(): bool
    {
        return $this->connected;
    }

    /**
     * Hand mail from a native callback to the loop. Before joinLoop() it waits here.
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
     * Hold mail under $key until flushLatest(), replacing whatever is pending there.
     */
    public function postLatest(string $key, object $mail): void
    {
        $this->latest[$key] = $mail;
    }

    /**
     * Deliver the pending latest mail, so a burst within one wait becomes one mail per key.
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
     * Drop the mail pending under $key, if any: its target is gone.
     */
    public function forgetLatest(string $key): void
    {
        unset($this->latest[$key]);
    }

    /**
     * Put this toolkit's native wait in charge of the loop's sleep and fold the loop's
     * waiter into it, so every wake of the loop ends the toolkit's sleep too.
     *
     * @throws ToolkitRuntimeException Unless connected, or when the loop's waiter has no descriptor.
     */
    public function joinLoop(LoopInterface $loop): void
    {
        if (! $this->connected) {
            throw new ToolkitRuntimeException('Connect the session before joining a loop.');
        }

        if ($this->loop === $loop) {
            return;
        }

        $descriptor = $loop->descriptor();
        if (is_null($descriptor)) {
            throw new ToolkitRuntimeException('The loop waiter has no descriptor: install ext-kqueue (macOS) or ext-epoll (Linux) and set io-pools.pool_waiters.default accordingly.');
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

    public function leaveLoop(): void
    {
        if (! is_null($this->loop)) {
            $this->loop->forget($this->pumpName());
            $this->releaseWakeDescriptor();
            $this->loop = null;
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
     * Take the session down. The engine stays initialized: connect() brings it back.
     */
    public function disconnect(): void
    {
        if (! $this->connected) {
            return;
        }

        $this->disconnectEngine();
        $this->connected = false;
        unset(self::live()[$this]);
    }

    /**
     * Start the engine on first use, and never again for the life of the process.
     * @return void
     */
    protected function initialize(): void
    {
        if (! $this->initialized) {
            $this->initializeEngine();
            $this->initialized = true;
        }
    }

    protected static function live(): WeakMap
    {
        return self::$live ??= new WeakMap();
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
     * @throws ToolkitRuntimeException Another toolkit's session is connected on macOS.
     * @throws ReflectionException
     */
    private function gateAdditionalSessions(): void
    {
        if(device_os_family() == 'mac')
        {
            foreach (self::live() as $session => $_)
            {
                // @todo - this method seems unnecessary anymore.. consider removing
                if($session->getClass() !== static::class && $session->connected())
                {
                $theirs = new ReflectionClass($session)->getShortName();
                $ours = new ReflectionClass($this)->getShortName();

                throw new ToolkitRuntimeException("{$theirs} is connected. On macOS one toolkit session pumps the application's events: disconnect it before connecting {$ours}.");
                }
            }
        }
    }
}