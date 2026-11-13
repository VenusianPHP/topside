<?php

namespace Surface\Contracts\Rendering\Raycasting;

/**
 * Where the casting runs. Mints raycasters; callers never name a raycaster class.
 *
 * A driver that cannot run in this PHP (its extension is not loaded, or is too old) throws a
 * RaycastException from its constructor: the 'auto' driver then takes the next one.
 */
interface RaycastDriver
{
    /** The name the driver is registered under: 'native' and 'ext' are VelvetGE's, a library binding has its own. */
    public function driver(): string;

    public function caster(Grid $grid): Raycaster;
}
