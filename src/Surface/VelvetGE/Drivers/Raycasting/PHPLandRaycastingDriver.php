<?php

namespace Surface\VelvetGE\Drivers\Raycasting;

use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Rendering\Raycasting\RaycastingDriver;
use Surface\VelvetGE\Raycasting\PHPLandRaycaster;

/** The casting in PHP. Always available: what 'auto' takes last. */
class PHPLandRaycastingDriver extends RaycastingDriver
{
    public function driver(): string
    {
        return 'native';
    }

    public function caster(Grid $grid): PHPLandRaycaster
    {
        return new PHPLandRaycaster($grid);
    }
}
