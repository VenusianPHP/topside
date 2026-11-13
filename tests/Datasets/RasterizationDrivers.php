<?php

declare(strict_types=1);

use Surface\VelvetGE\Drivers\Rasterization\CLandRasterizationDriver;
use Surface\VelvetGE\Drivers\Rasterization\PHPLandRasterizationDriver;
use Venusian\Topside\Tests\Fixtures\CoreOnlyScanner;
use Venusian\Topside\Tests\Fixtures\DirectPathScanner;
use Venusian\Topside\Tests\Fixtures\MaskPathScanner;
use Venusian\Topside\Tests\Fixtures\PartialRasterizationDriver;
use Venusian\Topside\Tests\Fixtures\ShapesAndBatchScanner;
use Venusian\Topside\Tests\Support\LibraryDrivers;

/**
 * Every rasterization driver this PHP can run: VelvetGE's native always and its ext when
 * ext-rasterize is loaded, and four stand-ins for library bindings: two whose scanners leave tiers
 * out, one that draws in place, one that answers masks. Every one answers VelvetGE's exact span bytes, and the
 * tests over this dataset hold them to that.
 */
dataset('rasterization drivers', function () {
    yield 'native' => [new PHPLandRasterizationDriver()];

    if (class_exists(RasterScanner::class)) {
        yield 'ext' => [new CLandRasterizationDriver()];
    }

    yield 'a path-only scanner' => [new PartialRasterizationDriver('core-only', CoreOnlyScanner::class)];
    yield 'a scanner with shapes and a batch, no pixel tier' => [new PartialRasterizationDriver('shapes-and-batch', ShapesAndBatchScanner::class)];
    yield 'a scanner that draws its paths in place' => [new PartialRasterizationDriver('direct-path', DirectPathScanner::class)];
    yield 'a scanner that answers coverage as masks' => [new PartialRasterizationDriver('mask-path', MaskPathScanner::class)];
});

/** Those, and each library's: for what must hold whichever library does the scanning. */
dataset('every rasterization driver', function () {
    yield 'native' => [new PHPLandRasterizationDriver()];

    if (class_exists(RasterScanner::class)) {
        yield 'ext' => [new CLandRasterizationDriver()];
    }

    yield 'a path-only scanner' => [new PartialRasterizationDriver('core-only', CoreOnlyScanner::class)];
    yield 'a scanner with shapes and a batch, no pixel tier' => [new PartialRasterizationDriver('shapes-and-batch', ShapesAndBatchScanner::class)];
    yield 'a scanner that draws its paths in place' => [new PartialRasterizationDriver('direct-path', DirectPathScanner::class)];
    yield 'a scanner that answers coverage as masks' => [new PartialRasterizationDriver('mask-path', MaskPathScanner::class)];

    foreach (LibraryDrivers::$rasterization as $name => $driver) {
        yield $name => [$driver];
    }
});
