<?php

namespace Surface\Contracts\Toolkits\Mail\Menus;

/**
 * The user flipped a toggle that has an id.
 */
readonly class MenuItemToggled extends MenuEventOccurred
{
    public function __construct(
        ?string $window,
        string $path,
        public bool $on,
    ) {
        parent::__construct($window, $path);
    }

    public function name(): string
    {
        return "menu.toggled.{$this->path}";
    }
}
