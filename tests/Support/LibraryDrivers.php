<?php

declare(strict_types=1);

namespace Venusian\Topside\Tests\Support;

use Surface\Contracts\Rendering\Rasterization\RasterizeDriver;

/**
 * Where a library package adds its drivers to the tests that hold for every driver, from its own
 * tests/Pest.php, before the datasets are read:
 *
 *     LibraryDrivers::$rasterization['plutovg'] = new PlutoVGRasterizationDriver();
 *
 * Pest reads tests/Pest.php before PHPUnit's bootstrap file, so the library's classes must be
 * autoloadable by then: require its autoloader at the top of that Pest.php.
 *
 * A library rasterizer answers its own coverage, so it joins the tests of what gets onto a
 * framebuffer ('every rasterization driver'), not the ones that hold VelvetGE's scanners to the
 * same bytes ('rasterization drivers'). A library framebuffer driver goes in 'framebuffer drivers'
 * with its Tolerance.
 */
final class LibraryDrivers
{
    /** @var array<string, RasterizeDriver> by the name a test shows */
    public static array $rasterization = [];
}
