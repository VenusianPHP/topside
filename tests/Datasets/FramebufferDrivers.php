<?php

declare(strict_types=1);

use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Venusian\Topside\Tests\Fixtures\CoreOnlyPixelStore;
use Venusian\Topside\Tests\Fixtures\MemoryPixelStore;
use Venusian\Topside\Tests\Fixtures\PaintingOnlyPixelStore;
use Venusian\Topside\Tests\Fixtures\PartialFramebufferDriver;

/**
 * Every framebuffer driver this PHP can run: VelvetGE's native always and its ext when ext-fb is
 * loaded, and stand-ins for library bindings: two whose stores leave tiers out, and one that keeps
 * its pixels in native memory (ext-fb's stands in for the library's). Whatever a test
 * holds for one driver it holds for all of them.
 */
dataset('framebuffer drivers', function () {
    yield 'native' => [new PHPLandFramebufferDriver()];

    if (CLandFramebufferDriver::available()) {
        yield 'ext' => [new CLandFramebufferDriver()];
    }

    yield 'a core-only store' => [new PartialFramebufferDriver('core-only', CoreOnlyPixelStore::class)];
    yield 'a store that paints but has no effects' => [new PartialFramebufferDriver('painting-only', PaintingOnlyPixelStore::class)];

    if (CLandFramebufferDriver::available()) {
        yield 'a library store in native memory' => [new PartialFramebufferDriver('memory', MemoryPixelStore::class)];
    }
});
