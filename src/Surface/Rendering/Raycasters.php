<?php

namespace Surface\Rendering;

use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Rendering\Raycasting\RaycastingDriver;
use Surface\Rendering\Raycasting\RaycastingLibraryManager;

/**
 * Rays through a grid of cells: what a ray hits, what a camera's columns hit, and the picture the camera sees.
 *
 *     $caster = Raycasters::driver()->caster(Grid::of($rows));
 *     $pixels = $caster->view(new Camera(2.5, 2.5), 320, 200, new Scenery([1 => 0x808080FF]));
 *     $frame->writeRgba8($pixels, 320, 200);
 *
 * Off unless config/rendering.php's raycaster.enabled is true (RENDERING_RAYCASTER=true).
 */
class Raycasters
{
    /** Whether the application turned raycasting on. */
    public static function enabled(): bool
    {
        return (bool) config('rendering.raycaster.enabled', false);
    }

    /** @throws RaycastException When raycasting is off. */
    public static function driver(?string $name = null): RaycastingDriver
    {
        return static::manager()->driver($name);
    }

    /** @throws RaycastException When raycasting is off. */
    public static function manager(): RaycastingLibraryManager
    {
        if (! static::enabled()) {
            throw RaycastException::disabled();
        }

        return app('raycasters');
    }
}
