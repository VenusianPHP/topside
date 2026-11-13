<?php

declare(strict_types=1);

use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Rasterization\CLandRasterizationDriver;
use Surface\VelvetGE\Drivers\Rasterization\PHPLandRasterizationDriver;

/** Every pairing of a rasterize driver with a framebuffer driver this PHP can run: what a software engine can be built from. */
dataset('engine pairings', function () {
    $rasterize = ['native' => new PHPLandRasterizationDriver()];
    $framebuffers = ['native' => new PHPLandFramebufferDriver()];
    if (class_exists(RasterScanner::class)) {
        $rasterize['extended'] = new CLandRasterizationDriver();
    }
    if (class_exists(FbBuffer::class)) {
        $framebuffers['extended'] = new CLandFramebufferDriver();
    }

    foreach ($rasterize as $r => $raster) {
        foreach ($framebuffers as $f => $framebuffer) {
            yield "{$r} geometry, {$f} bytes" => [$raster, $framebuffer];
        }
    }
});
