<?php

namespace Surface\Toolkits\Menus;

/**
 * The bar another window shows, followed live: what Menus::sharedWith('main') gives to
 * ToolkitWindows::open(..., menu:) and Menus::window().
 */
final readonly class SharedBar
{
    /**
     * @throws MenuException When no window is named.
     */
    public function __construct(
        public string $window,
    ) {
        if ($window === '') {
            throw new MenuException('Menus::sharedWith() needs the name of a window.');
        }
    }
}
