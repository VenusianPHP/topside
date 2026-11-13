<?php

namespace Surface\Graphics;

use Surface\Contracts\Graphics\GraphicsEngine;

/** The graphics engines, by name: Engines::engine('metal'). Null names config's graphics.default. */
class Engines
{
    public static function engine(?string $name = null): GraphicsEngine
    {
        return static::manager()->engine($name);
    }

    public static function manager(): GraphicsManager
    {
        return app('graphics');
    }
}
