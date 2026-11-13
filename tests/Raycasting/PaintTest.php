<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\FramebufferDriver;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\RaycastDriver;
use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Contracts\Rendering\Raycasting\Scenery;
use Surface\Contracts\Rendering\Raycasting\Texture;
use Venusian\Topside\Tests\Support\Framebuffers\Formats;

/*
 * Raycaster::paint() puts a view on a framebuffer. Whatever the raycaster and whichever store the
 * framebuffer keeps its pixels in, the pixels and the damage are those of view() and writeRgba8().
 */

function paintScenery(): Scenery
{
    return new Scenery([1 => 0xC04020FF, 2 => new Texture(hex2bin('aa0000ff'.'bb0000ff'.'cc0000ff'.'dd0000ff'), 2, 2), 3 => 0x20C040FF], 0x111111FF, 0x222222FF, 160);
}

function paintCamera(): Camera
{
    return new Camera(2.5, 2.5, 0.7, 1.1);
}

it('paints what view() and writeRgba8() put there, over the whole surface', function (RaycastDriver $caster, FramebufferDriver $frames, FormatSpec $spec): void {
    [$painted, $written] = [$frames->dirty($spec, 48, 32), $frames->dirty($spec, 48, 32)];
    $painted->beginEpoch() && $written->beginEpoch();
    $ray = $caster->caster(raycastRoom());

    $ray->paint(paintCamera(), paintScenery(), $painted);
    $written->writeRgba8($ray->view(paintCamera(), 48, 32, paintScenery()), 48, 32);

    expect(bin2hex($painted->dump()))->toBe(bin2hex($written->dump()))
        ->and($painted->damage())->toEqual($written->damage());
})->with('raycast drivers')->with('framebuffer drivers')->with([FormatSpec::rgba8(), FormatSpec::bgra8(), Formats::rgb565()]);

it('paints inside a rect of the surface and leaves the rest', function (RaycastDriver $caster, FramebufferDriver $frames, FormatSpec $spec): void {
    [$painted, $written] = [$frames->dirty($spec, 48, 32), $frames->dirty($spec, 48, 32)];
    foreach ([$painted, $written] as $frame) {
        $frame->fill(0x55);
        $frame->beginEpoch();
    }
    $ray = $caster->caster(raycastRoom());
    $where = new Region(7, 5, 30, 20);

    $ray->paint(paintCamera(), paintScenery(), $painted, $where);
    $written->writeRgba8($ray->view(paintCamera(), 30, 20, paintScenery()), 30, 20, 7, 5);

    expect(bin2hex($painted->dump()))->toBe(bin2hex($written->dump()))
        ->and($painted->damage())->toEqual($written->damage());
})->with('raycast drivers')->with('framebuffer drivers')->with([FormatSpec::rgba8(), Formats::rgb565()]);

it('refuses a rect that is not inside the surface, and a wall with no entry, writing nothing', function (RaycastDriver $caster, FramebufferDriver $frames): void {
    $frame = $frames->dirty(FormatSpec::rgba8(), 16, 12);
    $frame->beginEpoch();
    $blank = $frame->dump();
    $ray = $caster->caster(raycastRoom());

    expect(fn () => $ray->paint(paintCamera(), paintScenery(), $frame, new Region(10, 0, 8, 4)))->toThrow(RaycastException::class, 'is not inside the 16x12 framebuffer')
        ->and(fn () => $ray->paint(paintCamera(), paintScenery(), $frame, new Region(0, 0, 0, 4)))->toThrow(RaycastException::class, 'is not inside')
        ->and(fn () => $ray->paint(paintCamera(), paintScenery(), $frame, new Region(-1, 0, 4, 4)))->toThrow(RaycastException::class, 'is not inside')
        ->and(fn () => $ray->paint(new Camera(2.5, 3.5, 0.0, 1.0), new Scenery([1 => 0xFF0000FF]), $frame))->toThrow(RaycastException::class, 'Wall 2 has no colour or texture')
        ->and(bin2hex($frame->dump()))->toBe(bin2hex($blank))
        ->and($frame->damage())->toBe([]);
})->with('raycast drivers')->with('framebuffer drivers');
