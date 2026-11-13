<?php

declare(strict_types=1);

use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Venusian\Topside\Tests\Support\Framebuffers\FixtureRunner;

it('passes every golden fixture with its bytes in PHP', function (string $file): void {
    FixtureRunner::run(new PHPLandFramebufferDriver(), $file);
})->with(FixtureRunner::files());
