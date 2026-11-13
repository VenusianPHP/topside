<?php

namespace Surface\Toolkits\Drivers;

use Surface\Contracts\Toolkits\Composition;

abstract class ContentTreeDriver
{
    /**
     * Make the window visible.
     */
    abstract public function present(): void;

    /**
     * Show $view in the content area: build it the first time, show it again after that.
     */
    abstract public function render(Composition $view, array $args): void;
}