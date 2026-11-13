<?php

namespace Surface\VelvetGE\Drivers\Raycasting;

use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Marcher;
use Surface\VelvetGE\Raycasting\PHPLandMarcher;

/** The casting in PHP. Always available: what 'auto' takes last. */
class PHPLandRaycastingDriver extends MarcherRaycastingDriver
{
    public function driver(): string
    {
        return 'native';
    }

    protected function marcher(Grid $grid): Marcher
    {
        return new PHPLandMarcher($grid);
    }
}
