<?php

namespace Surface\Toolkits\Managers;

use Closure;
use Throwable;
use ReflectionMethod;
use ReflectionObject;
use Surface\Toolkits\Menus\OnMenu;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\SharedBar;
use Surface\Toolkits\Menus\StandardItem;
use Surface\Toolkits\Menus\MenuException;
use Surface\Toolkits\Menus\Runtime\MarkState;
use Surface\Toolkits\Menus\Runtime\WindowMenu;
use Surface\Toolkits\Menus\Runtime\PlannedNode;
use Surface\Toolkits\Menus\Runtime\MenuPlanner;
use Surface\Toolkits\Menus\Runtime\MenuMounter;
use Surface\Toolkits\Menus\Runtime\MenuNodeActivated;
use Surface\Toolkits\Views\Component;
use Surface\Contracts\Toolkits\ToolkitAdapterDriver;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\QuitRequested;
use Surface\Contracts\Toolkits\Mail\Menus\MenuMail;
use Surface\Contracts\Toolkits\Mail\Menus\MenuItemClicked;
use Surface\Contracts\Toolkits\Mail\Menus\MenuItemToggled;
use Surface\Contracts\Toolkits\Mail\Menus\MenuOptionChosen;
use Surface\Contracts\Toolkits\Menus\BarNodeKind;
use Surface\Contracts\Toolkits\Menus\StandardKind;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Contracts\Debug\ExceptionHandler;

/**
 * The menu profiles, and the bar of each open window.
 *
 * A window's bar is worked out in four steps, each given the bar from the one before: the
 * window's own, the route's, the controller's, the view's. Every window's bar is kept
 * current, so gaining focus only shows it.
 *
 * A bad menu is reported and the window keeps its last good bar. That holds where topside
 * runs menu code: working out and showing a bar, and what it runs for the application through
 * guarded(). An application's timers and own signal listeners are its own code.
 */
class MenuManager
{
    /** @var array<string, mixed> each profile as the config has it */
    protected readonly array $profiles;

    protected readonly ?string $default;

    protected readonly bool $mac;

    /** @var array<string, WindowMenu> */
    protected array $windows = [];

    /** On macOS: the bar shown while no window is focused. */
    protected ?WindowMenu $unfocused = null;

    /** The window that shows routed views. */
    protected ?string $host = null;

    protected ?string $focused = null;

    /** @var array<string, true> kinds already logged as unsupported, by toolkit */
    protected array $logged = [];

    /**
     * @param array{default_menu?: string|null, menus?: array<string, mixed>} $config toolkits.windows
     * @param bool|null $mac whether the platform is macOS; null to look
     */
    public function __construct(
        array $config = [],
        protected readonly ?FrameworkCore $app = null,
        ?bool $mac = null,
    ) {
        $this->default = $config['default_menu'] ?? null;
        $this->profiles = $config['menus'] ?? [];
        $this->mac = $mac ?? PHP_OS_FAMILY === 'Darwin';
    }

    /*
    |----------------------------------------------------------------------
    | Profiles
    |----------------------------------------------------------------------
    */

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->profiles);
    }

    /**
     * The bar of the profile named $name.
     *
     * @throws MenuException When there is no such profile, it is not a MenuBar, or it holds a callback.
     */
    public function profile(string $name): MenuBar
    {
        if (! array_key_exists($name, $this->profiles)) {
            $known = $this->profiles === [] ? 'None is defined' : 'Defined: '.implode(', ', $this->names());
            throw new MenuException("No menu profile named '{$name}'. {$known}.");
        }

        $bar = $this->profiles[$name];
        if (! $bar instanceof MenuBar) {
            throw new MenuException("Menu profile '{$name}' must be a MenuBar, not ".get_debug_type($bar).'. For a method, call it in the config file: EditorMenus::bar().');
        }

        if ($bar->callbacks() !== []) {
            $node = $bar->callbacks()[0];
            throw new MenuException("Menu profile '{$name}' is in the config file, so it cannot run callbacks: {$node->describe()} has one. Give it an id and listen for its mail.");
        }

        return $bar;
    }

    /**
     * The profile a window shows when nothing gives it another: toolkits.windows.default_menu.
     * Null when the config names none.
     *
     * @throws MenuException As profile().
     */
    public function default(): ?MenuBar
    {
        return is_null($this->default) ? null : $this->profile($this->default);
    }

    /*
    |----------------------------------------------------------------------
    | What an application calls, through Menus
    |----------------------------------------------------------------------
    */

    /**
     * Step 1 of a window that is open.
     */
    public function window(string $name, string|MenuBar|SharedBar|null $menu): void
    {
        $window = $this->windows[$name] ?? null;
        if (is_null($window)) {
            $this->report($name, new MenuException("Menus::window(): no window named '{$name}' is open."));

            return;
        }

        $window->source = $menu;
        $this->refresh($name);
    }

    /**
     * Step 3: the controller's, until the window opens another route.
     */
    public function page(string|MenuBar|Closure $menu): void
    {
        $window = $this->hosting();
        if (is_null($window)) {
            $this->report(null, new MenuException('Menus::page(): no window shows a route.'));

            return;
        }

        $window->page = $menu;
        $this->refresh($window->name);
    }

    /**
     * Hear menu mail until the window opens another route.
     */
    public function on(string $path, Closure $listener): void
    {
        $window = $this->hosting();
        if (is_null($window)) {
            $this->report(null, new MenuException("Menus::on('{$path}'): no window shows a route."));

            return;
        }

        $window->listeners[] = [$path, $listener];
    }

    /*
    |----------------------------------------------------------------------
    | What topside's windows, navigation and views tell it
    |----------------------------------------------------------------------
    */

    /**
     * A window opened. Its bar is built now, before the window is first shown.
     */
    public function opened(ToolkitPoweredWindow $window, ToolkitAdapterDriver $driver, string|MenuBar|SharedBar|null $menu = null): void
    {
        $name = $window->name();
        $record = $this->record($name, $driver, $window, $window->post(...));
        $record->source = $menu;
        $this->windows[$name] = $record;

        if ($this->mac && is_null($this->unfocused)) {
            $this->unfocused = $this->record(null, $driver, null, function (object $mail) use ($driver): void {
                $driver->session()?->post($mail);
            });
            $this->workOut($this->unfocused);
            if (is_null($this->focused)) {
                $this->display($this->unfocused);
            }
        }

        // Windows that were to share this one's bar before it was open follow from here.
        $this->refresh($name);
    }

    /**
     * A window is closing, while its native still lives.
     */
    public function closed(string $name): void
    {
        $window = $this->windows[$name] ?? null;
        if (is_null($window)) {
            return;
        }

        unset($this->windows[$name]);
        if ($this->host === $name) {
            $this->host = null;
        }

        try {
            $window->mounter->release();
        } catch (Throwable $failure) {
            $this->report($name, $failure);
        }

        $this->lostFocus($name);
    }

    /**
     * Called inside the adapter's own focus callback, not from mail: a shortcut is matched
     * against whichever bar is installed at that instant.
     */
    public function gainedFocus(string $name): void
    {
        $this->focused = $name;
        if (isset($this->windows[$name])) {
            $this->display($this->windows[$name]);
        }
    }

    public function lostFocus(string $name): void
    {
        if ($this->focused !== $name) {
            return;
        }

        $this->focused = null;
        if (! is_null($this->unfocused)) {
            $this->display($this->unfocused);
        }
    }

    /**
     * $name is the window routes and their views are shown in.
     */
    public function hosts(string $name): void
    {
        $this->host = $name;
    }

    /**
     * Open a route: its menu is step 2, what the last one's controller gave is dropped, and
     * the bar is worked out once, when $open returns.
     *
     * @param Closure(): mixed $open runs the controller
     */
    public function navigating(string|MenuBar|Closure|null $route, Closure $open): void
    {
        $window = $this->hosting();
        if (is_null($window)) {
            $this->guarded(null, $open);

            return;
        }

        $window->route = $route;
        $window->page = null;
        $window->listeners = [];

        $window->held++;
        try {
            $this->guarded($window->name, $open);
        } finally {
            $window->held--;
        }

        $this->refresh($window->name);
    }

    /**
     * A view was put on screen in a window: step 4.
     *
     * @param Closure(): void $rerender renders it again
     */
    public function showsView(string $name, Component $view, Closure $rerender): void
    {
        if (! isset($this->windows[$name])) {
            return;
        }

        [$this->windows[$name]->view, $this->windows[$name]->rerender] = [$view, $rerender];
        $this->refresh($name);
    }

    /**
     * Work a window's bar out again, and then that of every window sharing it.
     */
    public function refresh(string $name): void
    {
        $this->refreshOnce($name, []);
    }

    /**
     * Run application code that may build or change a menu: a bad one is reported instead of
     * thrown. Anything else it throws is the application's, and is left alone.
     *
     * @param Closure(): mixed $run
     */
    public function guarded(?string $window, Closure $run): void
    {
        try {
            $run();
        } catch (MenuException $failure) {
            $this->report($window, $failure);
        }
    }

    /*
    |----------------------------------------------------------------------
    | A node the user chose
    |----------------------------------------------------------------------
    */

    /**
     * Inside the toolkit's callback: a toggle's or choice's mark changes now, and the rest
     * waits for the loop.
     *
     * @param string|null $name null for the bar shown when no window is focused
     */
    public function activated(?string $name, string $uuid, bool|string|null $value = null): void
    {
        $window = $this->windowOrUnfocused($name);
        $mounted = $window?->mounter->find($uuid);
        if (is_null($mounted) || ! $mounted->node->enabled) {
            return;
        }

        $node = $mounted->node;
        switch ($node->kind) {
            case BarNodeKind::ITEM:
                $value = null;
                break;

            case BarNodeKind::STANDARD:
                if (! $node->replaced && $node->standard !== StandardKind::QUIT) {
                    return;    // the toolkit did the work
                }
                $value = null;
                break;

            case BarNodeKind::TOGGLE:
                $value = is_bool($value) ? $value : ! $node->on;
                if ($value === $node->on) {
                    return;
                }
                $this->mark($window, $mounted->planned, $value);
                break;

            case BarNodeKind::CHOICE:
                if (! is_string($value) || $value === $node->chosen || ! array_key_exists($value, $node->options)) {
                    return;
                }
                $this->mark($window, $mounted->planned, $value);
                break;

            default:
                return;
        }

        ($window->post)(new MenuNodeActivated($name, $uuid, $value));
    }

    /**
     * In a turn of the loop: the callback runs, the view of the window that owns the bar
     * renders again, and then the mail is posted.
     */
    public function deliver(MenuNodeActivated $mail): void
    {
        $window = $this->windowOrUnfocused($mail->window);
        $mounted = $window?->mounter->find($mail->uuid);
        if (is_null($mounted)) {
            return;    // gone since, as a removed button's click is
        }

        [$node, $planned] = [$mounted->node, $mounted->planned];
        $value = $mail->value;

        if ($node->kind === BarNodeKind::STANDARD && ! $node->replaced) {
            if ($node->standard === StandardKind::QUIT) {
                ($window->post)(new QuitRequested($mail->window));
            }

            return;
        }

        if (! is_null($callback = $planned->source->callback())) {
            $owner = $this->owner($window);
            $view = $owner->view;

            $this->guarded($mail->window, is_null($value) ? static fn () => $callback() : static fn () => $callback($value));

            $this->renderAgain($owner, $view);
            if ($window !== $owner && ! is_null($window->name)) {
                $this->refresh($window->name);
            }
        }

        if (! is_null($path = $planned->path)) {
            ($window->post)(match ($node->kind) {
                BarNodeKind::TOGGLE => new MenuItemToggled($mail->window, $path, (bool) $value),
                BarNodeKind::CHOICE => new MenuOptionChosen($mail->window, $path, (string) $value),
                default => new MenuItemClicked($mail->window, $path),
            });
        }
    }

    /**
     * Menu mail, for the #[OnMenu] methods of the views on screen and for Menus::on().
     */
    public function heard(MenuMail $mail): void
    {
        foreach ($this->windows as $window) {
            $view = $window->view;
            $listeners = $window->listeners;

            if (! is_null($view) && ($methods = $this->listening($view, $mail->path())) !== []) {
                foreach ($methods as $method) {
                    if ($window->view !== $view) {
                        break;    // it left the screen
                    }
                    $this->guarded($window->name, static fn () => $method->invoke($view, $mail));
                }
                $this->renderAgain($window, $view);
            }

            foreach ($listeners as $listener) {
                // One that a handler above navigated away from has ended.
                if ($listener[0] === $mail->path() && in_array($listener, $window->listeners, true)) {
                    $this->guarded($window->name, static fn () => $listener[1]($mail));
                }
            }
        }
    }

    /*
    |----------------------------------------------------------------------
    | Working a bar out
    |----------------------------------------------------------------------
    */

    /**
     * @param array<string, true> $done the windows already worked out in this pass
     */
    protected function refreshOnce(string $name, array $done): void
    {
        $window = $this->windows[$name] ?? null;
        if (is_null($window) || isset($done[$name]) || $window->held > 0 || $window->working) {
            return;
        }

        if ($this->workOut($window)) {
            $this->refreshSharing($name, [...$done, $name => true]);
        }
    }

    /**
     * @param array<string, true> $done
     */
    protected function refreshSharing(string $name, array $done = []): void
    {
        foreach ($this->windows as $other => $window) {
            if ($window->source instanceof SharedBar && $window->source->window === $name) {
                $this->refreshOnce((string) $other, $done);
            }
        }
    }

    /**
     * @return bool whether it worked; when not, the window keeps its last good bar
     */
    protected function workOut(WindowMenu $window): bool
    {
        $window->working = true;
        try {
            $bar = $this->resolve($window);
            $owner = $this->owner($window);
            $plan = $this->planner($window)->plan($bar, $owner->marks);
            $window->mounter->show($plan);

            $window->bar = $bar;
            $window->failed = null;
            if ($owner === $window) {
                $this->dropStaleMarks($window, $plan);
            }

            return true;
        } catch (Throwable $failure) {
            $this->report($window->name, $failure, $window);

            return false;
        } finally {
            $window->working = false;
        }
    }

    /**
     * The four steps.
     *
     * @throws Throwable What a step threw.
     */
    protected function resolve(WindowMenu $window): ?MenuBar
    {
        $source = $window->source;
        $bar = match (true) {
            is_null($source) => $this->default(),
            $source instanceof MenuBar => $source,
            $source instanceof SharedBar => $this->shared($window, $source),
            default => $this->profile($source),
        };

        $bar = $this->step($bar, $window->route, "the route's menu");
        $bar = $this->step($bar, $window->page, 'Menus::page()');

        if (! is_null($view = $window->view)) {
            $given = $bar ?? MenuBar::make();
            $changed = $view->menu($given);
            $bar = $changed === $given ? $bar : $changed;
        }

        return $bar;
    }

    protected function step(?MenuBar $bar, string|MenuBar|Closure|null $menu, string $what): ?MenuBar
    {
        if (is_null($menu)) {
            return $bar;
        }
        if ($menu instanceof MenuBar) {
            return $menu;
        }
        if (is_string($menu)) {
            return $this->profile($menu);
        }

        $given = $bar ?? MenuBar::make();
        $changed = $menu($given);
        if (! $changed instanceof MenuBar) {
            throw new MenuException("The closure of {$what} must return a MenuBar; it returned ".get_debug_type($changed).'.');
        }

        return $changed === $given ? $bar : $changed;
    }

    /**
     * @throws MenuException When the window shared with is not open, or sharing goes in a circle.
     */
    protected function shared(WindowMenu $window, SharedBar $shared): ?MenuBar
    {
        $seen = [$window->name];
        $at = $shared;
        while ($at instanceof SharedBar) {
            if (in_array($at->window, $seen, true)) {
                throw new MenuException('Windows share a bar in a circle: '.implode(', ', [...$seen, $at->window]).'.');
            }
            $seen[] = $at->window;
            $at = ($this->windows[$at->window] ?? null)?->source;
        }

        $other = $this->windows[$shared->window]
            ?? throw new MenuException("The bar of window '{$shared->window}' is shared, and that window is not open.");

        return $other->bar;
    }

    /**
     * The window whose bar a window shows: itself, or the one at the end of the sharing.
     */
    protected function owner(WindowMenu $window): WindowMenu
    {
        $owner = $window;
        $seen = [];
        while ($owner->source instanceof SharedBar && ! isset($seen[$owner->source->window])) {
            $seen[$owner->source->window] = true;
            if (! isset($this->windows[$owner->source->window])) {
                break;
            }
            $owner = $this->windows[$owner->source->window];
        }

        return $owner;
    }

    protected function mark(WindowMenu $window, PlannedNode $planned, bool|string $value): void
    {
        $source = $planned->source;
        $base = $planned->node->kind === BarNodeKind::TOGGLE ? $source->on : $source->chosen;

        $owner = $this->owner($window);
        $owner->marks[$planned->at] = new MarkState($base, $value, ! is_null($planned->path) && is_null($source->callback()));

        if (is_null($owner->name)) {
            $this->workOut($owner);

            return;
        }

        $this->refresh($owner->name);
        if ($window !== $owner) {
            $this->refresh($window->name);
        }
    }

    /**
     * @param list<PlannedNode> $plan
     */
    protected function dropStaleMarks(WindowMenu $window, array $plan): void
    {
        if ($window->marks === []) {
            return;
        }

        $defined = [];
        $walk = function (array $nodes) use (&$walk, &$defined): void {
            foreach ($nodes as $node) {
                if ($node->node->kind === BarNodeKind::TOGGLE) {
                    $defined[$node->at] = $node->source->on;
                } elseif ($node->node->kind === BarNodeKind::CHOICE) {
                    $defined[$node->at] = $node->source->chosen;
                }
                $walk($node->children);
            }
        };
        $walk($plan);

        foreach ($window->marks as $at => $mark) {
            $stale = array_key_exists($at, $defined) ? $defined[$at] !== $mark->base : ! $mark->kept;
            if ($stale) {
                unset($window->marks[$at]);
            }
        }
    }

    /**
     * Render the view that was on screen when a handler began, unless the handler left it or
     * closed its window.
     */
    protected function renderAgain(WindowMenu $window, ?Component $view): void
    {
        if ($window->view !== $view || (! is_null($window->name) && ($this->windows[$window->name] ?? null) !== $window)) {
            return;
        }

        if (! is_null($view) && ! is_null($window->rerender)) {
            ($window->rerender)();
        } elseif (! is_null($window->name)) {
            $this->refresh($window->name);
        }
    }

    /**
     * @return list<ReflectionMethod>
     */
    protected function listening(Component $view, string $path): array
    {
        $methods = [];
        foreach ((new ReflectionObject($view))->getMethods() as $method) {
            foreach ($method->getAttributes(OnMenu::class) as $attribute) {
                if ($attribute->newInstance()->path === $path) {
                    $methods[] = $method;
                    break;
                }
            }
        }

        return $methods;
    }

    protected function planner(WindowMenu $window): MenuPlanner
    {
        return new MenuPlanner(
            $this->mac,
            (string) ($this->app?->get('config')->get('app.name') ?? 'Venusian'),
            fn (StandardItem $item): bool => $this->supported($window, $item),
        );
    }

    protected function supported(WindowMenu $window, StandardItem $item): bool
    {
        $bar = $window->mounter->bar;
        if (is_null($bar) || $item->isReplaced() || $bar->supports($item->kind)) {
            return true;
        }

        $once = $bar::class.':'.$item->kind->value;
        if (! isset($this->logged[$once])) {
            $this->logged[$once] = true;
            $this->log(class_basename($bar)." cannot do the standard menu item '{$item->kind->value}': it is shown disabled.");
        }

        return false;
    }

    protected function record(?string $name, ToolkitAdapterDriver $driver, ?ToolkitPoweredWindow $window, Closure $post): WindowMenu
    {
        $bar = null;
        try {
            $bar = $driver->menuBar($window);
        } catch (Throwable $failure) {
            $this->report($name, $failure);
        }

        return new WindowMenu($name, new MenuMounter($bar), $post);
    }

    protected function display(WindowMenu $window): void
    {
        try {
            $window->mounter->bar?->show();
        } catch (Throwable $failure) {
            $this->report($window->name, $failure, $window);
        }
    }

    protected function hosting(): ?WindowMenu
    {
        return is_null($this->host) ? null : ($this->windows[$this->host] ?? null);
    }

    protected function windowOrUnfocused(?string $name): ?WindowMenu
    {
        return is_null($name) ? $this->unfocused : ($this->windows[$name] ?? null);
    }

    /**
     * Through the application's exception handler, naming the window and the error.
     *
     * @param WindowMenu|null $record given when the same failure can come at each render
     */
    protected function report(?string $window, Throwable $failure, ?WindowMenu $record = null): void
    {
        $where = is_null($window) ? 'Menus' : "The menus of window '{$window}'";
        $message = "{$where}: {$failure->getMessage()}";

        if (! is_null($record)) {
            if ($record->failed === $message) {
                return;
            }
            $record->failed = $message;
        }

        try {
            if ($this->app?->isBound(ExceptionHandler::class)) {
                $this->app->make(ExceptionHandler::class)->report(new MenuException($message, 0, $failure));

                return;
            }
        } catch (Throwable) {
            // A handler that fails must not end what a bad menu may not.
        }

        error_log($message);
    }

    protected function log(string $message): void
    {
        try {
            if ($this->app?->isBound('log')) {
                $this->app->make('log')->warning($message);

                return;
            }
        } catch (Throwable) {
            // As report().
        }

        error_log($message);
    }
}
