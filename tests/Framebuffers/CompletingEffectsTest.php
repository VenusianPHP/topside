<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\FramebufferDriver;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Surface\VelvetGE\Framebuffers\CompletingPixelStore;
use Venusian\Topside\Tests\Fixtures\CoreOnlyPixelStore;
use Venusian\Topside\Tests\Fixtures\PartialFramebufferDriver;

/*
 * The blur pyramid and a glass pane on every driver: frames of one driver feed each other, and
 * every driver ends with the bytes the PHP store ends with. For a store without the effects
 * tier this is VelvetGE's arithmetic run over the store's own pixels.
 */

/** A 16x12 frame of seeded noise, a level down, a level up, and a pane of glass over a second noise frame. */
function glassOn(FramebufferDriver $driver): array
{
    $random = new Random\Randomizer(new Random\Engine\Mt19937(7));
    $scene = $driver->full(FormatSpec::rgba8(), 16, 12)->writeRgba8($random->getBytes(16 * 12 * 4), 16, 12);
    $down = $scene->scratch(8, 6)->kawaseDown($scene, new Region(0, 0, 16, 12));
    $up = $scene->scratch(16, 12)->kawaseUp($down);
    $pane = $driver->dirty(FormatSpec::rgba8(), 16, 12)->writeRgba8($random->getBytes(16 * 12 * 4), 16, 12);
    $pane->beginEpoch();
    $pane->paintGlass([2.0, 2.0, 14.0, 10.0, [3.0, 3.0, 3.0, 3.0]], $down, 2, $scene, 0, 0, [0.5, 4.0, 1.0, 1.2, 0.3, 0.05, 0xFFFFFF40, 0xFFFFFF99, 1.0], new Region(0, 0, 16, 12));

    return [bin2hex($down->toRgba8()), bin2hex($up->toRgba8()), bin2hex($pane->toRgba8()), array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $pane->damage())];
}

it('builds the blur levels and paints glass to the PHP store\'s bytes and damage', function (FramebufferDriver $driver): void {
    [$down, $up, $pane, $damage] = glassOn($driver);
    [$native_down, $native_up, $native_pane, $native_damage] = glassOn(new PHPLandFramebufferDriver());

    expect($down)->toBe($native_down, 'the level down')
        ->and($up)->toBe($native_up, 'the level up')
        ->and($pane)->toBe($native_pane, 'the pane')
        ->and($damage)->toBe($native_damage)
        ->and($damage)->not->toBe([]);
})->with('framebuffer drivers');

it('completes a store that leaves tiers out, and leaves a complete one as it is', function (): void {
    $partial = (new PartialFramebufferDriver('core-only', CoreOnlyPixelStore::class))->full(FormatSpec::rgba8(), 4, 4);
    $native = (new PHPLandFramebufferDriver())->full(FormatSpec::rgba8(), 4, 4);

    expect($partial->store())->toBeInstanceOf(CompletingPixelStore::class)
        ->and($partial->store()->inner())->toBeInstanceOf(CoreOnlyPixelStore::class)
        ->and($native->store())->not->toBeInstanceOf(CompletingPixelStore::class)
        ->and($partial->scratch(2, 2)->store())->toBeInstanceOf(CompletingPixelStore::class);
});

it('writes nothing back when the lent call refuses', function (): void {
    $frame = (new PartialFramebufferDriver('core-only', CoreOnlyPixelStore::class))->full(FormatSpec::rgba8(), 4, 4)->fill(0x11223344);
    $before = $frame->toRgba8();

    expect(fn () => $frame->paintGradient('', 'not a ramp', 'linear', Surface\NutsAndBolts\GradientSpread::PAD, [1, 0, 0, 1, 0, 0]))
        ->toThrow(Surface\Contracts\Rendering\Framebuffers\FramebufferException::class)
        ->and($frame->toRgba8())->toBe($before);
});
