<?php

declare(strict_types=1);

use Venusian\Topside\Tests\Fixtures\CoreOnlyPixelStore;
use Venusian\Topside\Tests\Fixtures\PaintingOnlyPixelStore;
use Venusian\Topside\Tests\Fixtures\PartialFramebufferDriver;
use Venusian\Topside\Tests\Support\Framebuffers\FixtureRunner;

/*
 * A store that leaves tiers out is completed by VelvetGE, and the result is held to the same
 * golden bytes as VelvetGE's own stores: a library binding is swappable from its first core
 * method.
 */

it('passes every golden fixture with a store that has the core only', function (string $file): void {
    FixtureRunner::run(new PartialFramebufferDriver('core-only', CoreOnlyPixelStore::class), $file, addressed: false);
})->with(FixtureRunner::files());

it('passes every golden fixture with a store that paints but has no effects', function (string $file): void {
    FixtureRunner::run(new PartialFramebufferDriver('painting-only', PaintingOnlyPixelStore::class), $file, addressed: false);
})->with(FixtureRunner::files());
