<?php

namespace Surface\Toolkits\Drivers;

use Surface\Toolkits\Views\Component;
use Surface\Contracts\Toolkits\Mail\PrimitiveMail;

abstract class ContentTreeDriver
{
    /**
     * Make the window visible.
     */
    abstract public function present(): void;

    abstract public function boot(int $width, int $height): void;

    /**
     * Put a view on screen in place of the one there.
     */
    abstract public function render(Component $view): void;

    /**
     * Mail a primitive posted: the on-screen view's handler for it runs.
     */
    abstract public function deliver(PrimitiveMail $mail): void;
}
