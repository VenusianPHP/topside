<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Face;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Hit;
use Surface\Contracts\Rendering\Raycasting\Hits;
use Surface\Contracts\Rendering\Raycasting\RaycastDriver;
use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Contracts\Rendering\Raycasting\Scenery;
use Surface\Contracts\Rendering\Raycasting\Texture;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;

/*
 * The contract on every driver. Expected hits and pictures are worked by hand from the grid
 * walk; the cameras look east (angle 0), where the direction is exactly (1, 0).
 */

/** Walls all round, wall 2 in the east wall on row 3, wall 3 in the south wall on column 1. */
function raycastRoom(): Grid
{
    return Grid::of([
        [1, 1, 1, 1, 1],
        [1, 0, 0, 0, 1],
        [1, 0, 0, 0, 1],
        [1, 0, 0, 0, 2],
        [1, 3, 1, 1, 1],
    ]);
}

/** A camera looking east whose plane is half as long as its direction: tan(fov / 2) = 0.5. */
function eastward(float $x, float $y): Camera
{
    return new Camera($x, $y, 0.0, 2 * atan(0.5));
}

/** @return list<list<string>> rows of pixels, eight hex digits each */
function raycastPicture(string $rgba8, int $width): array
{
    return array_map(fn (string $row): array => str_split(bin2hex($row), 8), str_split($rgba8, $width * 4));
}

it('names itself and keeps its grid', function (RaycastDriver $driver): void {
    $grid = raycastRoom();
    $caster = $driver->caster($grid);

    expect($caster->driver())->toBe($driver->driver())->toBeIn(['native', 'ext'])
        ->and($caster->grid())->toBe($grid);
})->with('raycast drivers');

it('casts one ray to the first wall, with its cell, id, face and place on the face', function (RaycastDriver $driver): void {
    $caster = $driver->caster(raycastRoom());

    expect($caster->cast(2.5, 2.5, 0.0))->toEqual(new Hit(1.5, 0.5, 4, 2, 1, Face::WEST))
        ->and($caster->cast(2.5, 3.25, 0.0))->toEqual(new Hit(1.5, 0.25, 4, 3, 2, Face::WEST))
        ->and($caster->cast(2.5, 2.5, M_PI)->face)->toBe(Face::EAST)
        ->and($caster->cast(2.5, 2.5, M_PI / 2)->face)->toBe(Face::NORTH)
        ->and($caster->cast(2.5, 2.5, -M_PI / 2)->face)->toBe(Face::SOUTH)
        ->and($caster->cast(1.25, 2.5, M_PI / 2)->wall)->toBe(3)
        ->and($caster->cast(2.5, 2.5, M_PI / 2)->distance)->toEqualWithDelta(1.5, 1e-12);
})->with('raycast drivers');

it('hits nothing past its reach, outside the grid, or in an open grid', function (RaycastDriver $driver): void {
    $caster = $driver->caster(raycastRoom());

    expect($caster->cast(2.5, 2.5, 0.0, 1.4))->toBeNull()
        ->and($caster->cast(2.5, 2.5, 0.0, 1.5))->not->toBeNull()
        ->and($caster->cast(-1.0, 2.5, 0.0))->toBeNull()
        ->and($driver->caster(Grid::of([[0, 0, 0]]))->cast(0.5, 0.5, 0.0))->toBeNull();
})->with('raycast drivers');

it('fans one ray a column, left to right, a flat wall at one distance', function (RaycastDriver $driver): void {
    $hits = Hits::unpack($driver->caster(raycastRoom())->fan(eastward(2.5, 2.5), 2));

    expect($hits)->toHaveCount(2)
        ->and([$hits[0]->x, $hits[0]->y, $hits[0]->wall, $hits[0]->face])->toBe([4, 2, 1, Face::WEST])
        ->and($hits[0]->distance)->toEqualWithDelta(1.5, 1e-12)
        ->and($hits[1]->distance)->toEqualWithDelta(1.5, 1e-12)
        ->and($hits[0]->along)->toEqualWithDelta(0.125, 1e-12)
        ->and($hits[1]->along)->toEqualWithDelta(0.875, 1e-12)
        ->and($driver->caster(raycastRoom())->fan(eastward(2.5, 2.5), 3, 1.0))->toBe(str_repeat("\0", 3 * Hits::BYTES));
})->with('raycast drivers');

it('draws the ceiling, a centred wall line and the floor', function (RaycastDriver $driver): void {
    $scenery = new Scenery([1 => 0xFF0000FF, 2 => 0x00FF00FF], 0x111111FF, 0x222222FF);
    $caster = $driver->caster(raycastRoom());

    // Distance 1.5, height 4: a line (int) (4 / 1.5) = 2 tall from row 1.
    expect(raycastPicture($caster->view(eastward(2.5, 2.5), 1, 4, $scenery), 1))
        ->toBe([['111111ff'], ['ff0000ff'], ['ff0000ff'], ['222222ff']])
        // From (2.5, 3.0) the two columns reach wall 1 and wall 2.
        ->and(raycastPicture($caster->view(eastward(2.5, 3.0), 2, 2, $scenery), 2))
        ->toBe([['111111ff', '111111ff'], ['ff0000ff', '00ff00ff']])
        ->and(raycastPicture($caster->view(eastward(2.5, 2.5), 1, 4, $scenery, 1.0), 1))
        ->toBe([['111111ff'], ['111111ff'], ['222222ff'], ['222222ff']]);
})->with('raycast drivers');

it('paints a wall with a texture, and darkens north and south faces by the shade', function (RaycastDriver $driver): void {
    $texture = new Texture(hex2bin('aa0000ff'.'bb0000ff'.'cc0000ff'.'dd0000ff'), 2, 2);
    $caster = $driver->caster(raycastRoom());

    // Wall 2 from the west at along 0.25: texture column 0, top to bottom.
    expect(raycastPicture($caster->view(eastward(2.5, 3.25), 1, 4, new Scenery([2 => $texture], 0x111111FF, 0x222222FF)), 1))
        ->toBe([['111111ff'], ['aa0000ff'], ['cc0000ff'], ['222222ff']])
        // Looking south at a north face, shade 128: 255 * 128 / 255 = 128.
        ->and(raycastPicture($caster->view(new Camera(2.5, 2.5, M_PI / 2, 2 * atan(0.5)), 1, 4, new Scenery([1 => 0xFF8040C0], 0x111111FF, 0x222222FF, 128)), 1))
        ->toBe([['111111ff'], ['804020c0'], ['804020c0'], ['222222ff']]);
})->with('raycast drivers');

it('gives pixels a framebuffer takes as they are', function (RaycastDriver $driver): void {
    $pixels = $driver->caster(raycastRoom())->view(eastward(2.5, 2.5), 8, 6, new Scenery([1 => 0xFF0000FF, 2 => 0xFF0000FF], 0x111111FF, 0x222222FF));
    $frame = (new PHPLandFramebufferDriver())->full(FormatSpec::rgba8(), 8, 6)->writeRgba8($pixels, 8, 6);

    expect($frame->toRgba8())->toBe($pixels)
        ->and($frame->getPixel(0, 0))->toBe(0x111111FF)
        ->and($frame->getPixel(4, 3))->toBe(0xFF0000FF)
        ->and($frame->getPixel(7, 5))->toBe(0x222222FF);
})->with('raycast drivers');

it('refuses a ray that hits a wall the scenery has no entry for', function (RaycastDriver $driver): void {
    expect(fn () => $driver->caster(raycastRoom())->view(eastward(2.5, 3.5), 1, 4, new Scenery([1 => 0xFF0000FF])))
        ->toThrow(RaycastException::class, 'Wall 2 has no colour or texture');
})->with('raycast drivers');

it('refuses a ray that is not finite, too far out, or has no reach', function (RaycastDriver $driver, array $arguments, string $message): void {
    expect(fn () => $driver->caster(raycastRoom())->cast(...$arguments))->toThrow(RaycastException::class, $message);
})->with('raycast drivers')->with([
    [[NAN, 2.5, 0.0], "A ray's x is not a finite number"],
    [[2.5, -INF, 0.0], "A ray's y is not a finite number"],
    [[2.5, 2.0 ** 31, 0.0], "A ray's y 2147483648 is past ±1073741824"],
    [[2.5, 2.5, INF], "A ray's angle is not a finite number"],
    [[2.5, 2.5, 0.0, 0.0], 'A reach is above 0, or INF; got 0'],
    [[2.5, 2.5, 0.0, NAN], 'A reach is above 0, or INF; got NAN'],
]);

it('refuses a fan or a view with a side out of range, or no reach', function (RaycastDriver $driver): void {
    $caster = $driver->caster(raycastRoom());
    $scenery = new Scenery([1 => 0xFF0000FF]);

    expect(fn () => $caster->fan(eastward(2.5, 2.5), 0))->toThrow(RaycastException::class, "A fan's columns is 1..65535, got 0")
        ->and(fn () => $caster->fan(eastward(2.5, 2.5), 65536))->toThrow(RaycastException::class, "A fan's columns is 1..65535, got 65536")
        ->and(fn () => $caster->fan(eastward(2.5, 2.5), 4, -1.0))->toThrow(RaycastException::class, 'A reach is above 0')
        ->and(fn () => $caster->view(eastward(2.5, 2.5), 0, 4, $scenery))->toThrow(RaycastException::class, "A view's width is 1..16384, got 0")
        ->and(fn () => $caster->view(eastward(2.5, 2.5), 4, 16385, $scenery))->toThrow(RaycastException::class, "A view's height is 1..16384, got 16385")
        ->and(fn () => $caster->view(eastward(2.5, 2.5), 4, 4, $scenery, 0.0))->toThrow(RaycastException::class, 'A reach is above 0');
})->with('raycast drivers');
