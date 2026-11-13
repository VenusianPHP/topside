<?php

declare(strict_types=1);

use Surface\VelvetGE\Drivers\Rasterization\CLandRasterizationDriver;
use Surface\VelvetGE\Drivers\Rasterization\PHPLandRasterizationDriver;
use Venusian\Topside\Tests\Fixtures\CoreOnlyScanner;
use Venusian\Topside\Tests\Fixtures\PartialRasterizationDriver;
use Venusian\Topside\Tests\Fixtures\ShapesAndBatchScanner;

/**
 * Every rasterization driver this PHP can run: VelvetGE's native always and its ext when
 * ext-rasterize is loaded, and two stand-ins for library bindings whose scanners leave tiers
 * out. Whatever a test holds for one driver it holds for all of them.
 */
dataset('rasterization drivers', function () {
    yield 'native' => [new PHPLandRasterizationDriver()];

    if (class_exists(RasterScanner::class)) {
        yield 'ext' => [new CLandRasterizationDriver()];
    }

    yield 'a path-only scanner' => [new PartialRasterizationDriver('core-only', CoreOnlyScanner::class)];
    yield 'a scanner with shapes and a batch, no pixel tier' => [new PartialRasterizationDriver('shapes-and-batch', ShapesAndBatchScanner::class)];
});
