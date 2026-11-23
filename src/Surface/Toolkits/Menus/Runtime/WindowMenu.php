<?php

namespace Surface\Toolkits\Menus\Runtime;

use Closure;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\SharedBar;
use Surface\Toolkits\Views\Component;

/**
 * Everything the menu runtime knows about one window, or about the bar shown when none is
 * focused: what each of the four steps was given, and what came of them.
 *
 * @internal The MenuManager's record.
 */
final class WindowMenu
{
    /** Step 1. */
    public string|MenuBar|SharedBar|null $source = null;

    /** Step 2. */
    public string|MenuBar|Closure|null $route = null;

    /** Step 3. */
    public string|MenuBar|Closure|null $page = null;

    /** Step 4: the view on screen. */
    public ?Component $view = null;

    /** @var (Closure(): void)|null renders that view again */
    public ?Closure $rerender = null;

    /** The last good bar: what a window sharing this one starts from. */
    public ?MenuBar $bar = null;

    /** @var list<array{string, Closure}> Menus::on(), until the window opens another route */
    public array $listeners = [];

    /** @var array<string, MarkState> */
    public array $marks = [];

    /** Over 0 while a controller runs: the bar is worked out once, when it returns. */
    public int $held = 0;

    public bool $working = false;

    /** The failure last reported, so each render does not report it again. */
    public ?string $failed = null;

    /**
     * @param string|null $name null for the bar shown when no window is focused
     * @param Closure(object): void $post
     */
    public function __construct(
        public readonly ?string $name,
        public readonly MenuMounter $mounter,
        public readonly Closure $post,
    ) {}
}
