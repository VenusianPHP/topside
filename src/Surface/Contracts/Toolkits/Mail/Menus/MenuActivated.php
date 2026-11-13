<?php

namespace Surface\Contracts\Toolkits\Mail\Menus;

use Surface\Contracts\Toolkits\Mail\Windows\WindowMail;
use Surface\Contracts\Toolkits\Mail\Windows\WindowEventOccurred;

readonly class MenuActivated extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public readonly string $item,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "menu.activated.{$this->window}.{$this->item}";
    }
}