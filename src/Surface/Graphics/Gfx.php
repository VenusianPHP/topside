<?php

namespace Surface\Graphics;

use Closure;
use ReflectionException;
use Surface\Contracts\Graphics\Backend;
use Surface\Contracts\Graphics\GraphicsEngine;

/**
 * The way in to the graphics engines:
 *
 *     $surface = Gfx::engine('velvet')->surface($stage);
 *     $surface->render(fn ($g) => $g->fillRect(10, 10, 50, 50, $red))->present();
 */
class Gfx
{
    /**
     * @param string|null $name null: graphics.engine.default
     * @param string|null $backend 'cpu', 'gpu' or 'auto'; null: graphics.engine.backend
     *
     * @throws ReflectionException
     */
    public static function engine(?string $name = null, ?string $backend = null): GraphicsEngine
    {
        return static::manager()->engine($name, $backend);
    }

    /**
     * Registers an engine, with the backends it offers.
     *
     * @param Closure(Backend): GraphicsEngine $creator
     *
     * @throws ReflectionException
     */
    public static function extend(string $name, Closure $creator, Backend ...$backends): void
    {
        static::manager()->extend($name, $creator, ...$backends);
    }

    /**
     * @throws ReflectionException
     */
    public static function manager(): GraphicsEngineDeviceManager
    {
        return app('gfx');
    }
}
