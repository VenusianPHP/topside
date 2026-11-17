<?php

namespace Surface\Graphics;

use Surface\Contracts\Graphics\Backend;
use Surface\Contracts\Graphics\GraphicsEngine;

abstract class GfxDevice implements GraphicsEngine
{
    public function __construct(
        protected readonly Backend $backend
    ) {}

    public function backend(): Backend
    {
        return $this->backend;
    }

    /**
     * An engine made of swappable parts names them here. One that is a single library has none.
     */
    public function modules(): array
    {
        return [];
    }

    /**
     * Will let go of what the engine holds in its library. An engine that holds nothing leaves this as it is.
     */
    public function release(): void {}
}