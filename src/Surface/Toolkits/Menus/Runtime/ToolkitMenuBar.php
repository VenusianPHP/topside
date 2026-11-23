<?php

namespace Surface\Toolkits\Menus\Runtime;

use Surface\Toolkits\ToolkitSession;
use Surface\Toolkits\Managers\MenuManager;
use Surface\Contracts\Toolkits\Menus\TKMenuBar;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;

/**
 * The base of each adapter's bar. An adapter makes the natives the operations ask for, and
 * from a native's own signal calls activated() with the node's uuid and nothing else.
 */
abstract class ToolkitMenuBar implements TKMenuBar
{
    /**
     * @param ToolkitPoweredWindow|null $window null for the bar shown when no window is focused
     */
    public function __construct(
        protected readonly ToolkitSession $session,
        protected readonly ?ToolkitPoweredWindow $window = null,
    ) {}

    /**
     * The user chose a node. The mark of a toggle or choice changes now, through change();
     * the handler runs in a later turn of the loop.
     *
     * @param bool|string|null $value a toggle's new state (left out, it flips) or a choice's option
     */
    protected function activated(string $uuid, bool|string|null $value = null): void
    {
        app(MenuManager::class)->activated($this->window?->name(), $uuid, $value);
    }
}
