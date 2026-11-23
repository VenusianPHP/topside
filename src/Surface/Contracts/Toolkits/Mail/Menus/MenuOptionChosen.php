<?php

namespace Surface\Contracts\Toolkits\Mail\Menus;

/**
 * The user chose another option of a choice that has an id.
 */
readonly class MenuOptionChosen extends MenuEventOccurred
{
    public function __construct(
        ?string $window,
        string $path,
        public string $option,
    ) {
        parent::__construct($window, $path);
    }

    public function name(): string
    {
        return "menu.chosen.{$this->path}";
    }
}
