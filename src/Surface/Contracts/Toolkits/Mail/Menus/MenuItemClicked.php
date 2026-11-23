<?php

namespace Surface\Contracts\Toolkits\Mail\Menus;

/**
 * An item with an id was chosen.
 */
readonly class MenuItemClicked extends MenuEventOccurred
{
    public function name(): string
    {
        return "menu.clicked.{$this->path}";
    }
}
