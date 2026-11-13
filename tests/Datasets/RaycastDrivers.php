<?php

declare(strict_types=1);

use Surface\VelvetGE\Drivers\Raycasting\CLandRaycastingDriver;
use Surface\VelvetGE\Drivers\Raycasting\PHPLandRaycastingDriver;
use Venusian\Topside\Tests\Fixtures\LibraryRaycastingDriver;

/**
 * Every raycasting driver this PHP can run: VelvetGE's native always, and with ext-raycast its ext and a
 * stand-in for a library binding whose marcher draws in place (ext-raycast stands in for the library).
 */
dataset('raycast drivers', function () {
    yield 'native' => [new PHPLandRaycastingDriver()];

    if (CLandRaycastingDriver::available()) {
        yield 'ext' => [new CLandRaycastingDriver()];
        yield 'a library marcher that draws in place' => [new LibraryRaycastingDriver()];
    }
});
