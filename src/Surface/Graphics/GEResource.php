<?php

namespace Surface\Graphics;

use Surface\Contracts\Graphics\GraphicsException;

/** What a graphics engine hands out. It belongs to the engine that made it. */
abstract class GEResource
{
    public function __construct(
        protected readonly GraphicsEngine $engine,
    ) {}

    public function engine(): GraphicsEngine
    {
        return $this->engine;
    }

    /** @throws GraphicsException When $other was made by another engine. */
    protected function mine(GEResource $other): void
    {
        if ($other->engine !== $this->engine) {
            throw GraphicsException::foreignResource($this->engine->name(), $other->engine->name());
        }
    }
}
