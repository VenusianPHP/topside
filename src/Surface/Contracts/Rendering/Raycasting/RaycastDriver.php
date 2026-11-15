<?php

namespace Surface\Contracts\Rendering\Raycasting;

/**
 * Where the casting runs. Mints raycasters; callers never name a raycaster class.
 *
 * A driver that cannot run in this PHP (its extension is not loaded, or is too old) throws a
 * RaycastException from its constructor.
 */
interface RaycastDriver
{
    /** The name the driver is registered under, such as Lustrous's 'lustrous-php' and 'lustrous-c'. */
    public function driver(): string;

    public function caster(Grid $grid): Raycaster;
}
