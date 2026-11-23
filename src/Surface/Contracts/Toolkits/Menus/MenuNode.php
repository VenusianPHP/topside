<?php

namespace Surface\Contracts\Toolkits\Menus;

/**
 * Something a menu holds: an item, a toggle, a choice, a separator, a standard item or a
 * submenu. A description only; the bar on a window is built from it.
 */
interface MenuNode
{
    /**
     * What tells this node from its siblings: its id, else its standard kind, else its label
     * or title. Null for a node with none (a separator), which is told by its position.
     */
    public function key(): ?string;

    /**
     * Whether the node is part of the bar on this platform: hide, hide others and show all are
     * only on macOS.
     */
    public function shownOn(bool $mac): bool;
}
