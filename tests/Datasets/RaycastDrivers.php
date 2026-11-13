<?php

declare(strict_types=1);

use Surface\VelvetGE\Drivers\Raycasting\CLandRaycastingDriver;
use Surface\VelvetGE\Drivers\Raycasting\PHPLandRaycastingDriver;

/** Every VelvetGE raycasting driver this PHP can run: native always, ext when ext-raycast is loaded. */
dataset('raycast drivers', function () {
    yield 'native' => [new PHPLandRaycastingDriver()];

    if (CLandRaycastingDriver::available()) {
        yield 'ext' => [new CLandRaycastingDriver()];
    }
});
