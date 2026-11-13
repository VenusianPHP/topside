<?php

declare(strict_types=1);

use Random\Engine\Mt19937;
use Random\Randomizer;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Hits;
use Surface\Contracts\Rendering\Raycasting\Scenery;
use Surface\Contracts\Rendering\Raycasting\Texture;
use Surface\VelvetGE\Drivers\Raycasting\CLandRaycastingDriver;
use Surface\VelvetGE\Drivers\Raycasting\PHPLandRaycastingDriver;

/*
 * The PHP raycaster and ext-raycast answer the same bytes: the same hit records and the same
 * pictures, from seeded random grids, cameras and sceneries. This is what lets 'auto' pick
 * either one.
 */

/** A grid with walls all round and about a fifth of the inside walled, ids 1..5. */
function randomGrid(Randomizer $random, int $width, int $height): Grid
{
    $rows = [];
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $edge = $x === 0 || $y === 0 || $x === $width - 1 || $y === $height - 1;
            $rows[$y][$x] = $edge || $random->getInt(0, 4) === 0 ? $random->getInt(1, 5) : 0;
        }
    }

    return Grid::of($rows);
}

/** A camera anywhere in or just outside the grid, looking anywhere, seeing 20 to 140 degrees. */
function randomCamera(Randomizer $random, Grid $grid): Camera
{
    return new Camera(
        $random->getFloat(-1.0, $grid->width + 1.0),
        $random->getFloat(-1.0, $grid->height + 1.0),
        $random->getFloat(-M_PI * 2, M_PI * 2),
        $random->getFloat(deg2rad(20), deg2rad(140)),
    );
}

/** Walls 1..5: colours and textures of odd sizes, a random ceiling, floor and shade. */
function randomScenery(Randomizer $random): Scenery
{
    $walls = [];
    for ($id = 1; $id <= 5; $id++) {
        if ($random->getInt(0, 1) === 0) {
            $walls[$id] = $random->getInt(0, 0xFFFFFFFF);

            continue;
        }
        [$width, $height] = [$random->getInt(1, 9), $random->getInt(1, 9)];
        $walls[$id] = new Texture($random->getBytes($width * $height * 4), $width, $height);
    }

    return new Scenery($walls, $random->getInt(0, 0xFFFFFFFF), $random->getInt(0, 0xFFFFFFFF), $random->getInt(0, 255));
}

beforeEach(function (): void {
    if (! CLandRaycastingDriver::available()) {
        $this->markTestSkipped('ext-raycast '.CLandRaycastingDriver::MINIMUM.' is not loaded in this PHP.');
    }
});

it('casts single rays to the same hits', function (): void {
    $random = new Randomizer(new Mt19937(1));
    $grid = randomGrid($random, 24, 17);
    [$native, $ext] = [(new PHPLandRaycastingDriver())->caster($grid), (new CLandRaycastingDriver())->caster($grid)];

    for ($step = 0; $step < 2000; $step++) {
        $x = $random->getFloat(-1.0, 25.0);
        $y = $random->getFloat(-1.0, 18.0);
        $angle = $random->getFloat(-M_PI * 2, M_PI * 2);
        $reach = $random->getInt(0, 3) === 0 ? $random->getFloat(0.1, 12.0) : INF;

        expect($ext->cast($x, $y, $angle, $reach))->toEqual($native->cast($x, $y, $angle, $reach), "ray {$step}");
    }
});

it('casts rays along the axes, from boundaries and through corners to the same hits', function (): void {
    $random = new Randomizer(new Mt19937(2));
    $grid = randomGrid($random, 12, 12);
    [$native, $ext] = [(new PHPLandRaycastingDriver())->caster($grid), (new CLandRaycastingDriver())->caster($grid)];

    foreach ([0.0, M_PI / 4, M_PI / 2, 3 * M_PI / 4, M_PI, -M_PI / 4, -M_PI / 2, -3 * M_PI / 4] as $angle) {
        for ($x = 0.0; $x <= 12.0; $x += 0.5) {
            for ($y = 0.0; $y <= 12.0; $y += 0.5) {
                expect($ext->cast($x, $y, $angle))->toEqual($native->cast($x, $y, $angle), "({$x}, {$y}) at {$angle}");
            }
        }
    }
});

it('fans to the same bytes', function (): void {
    $random = new Randomizer(new Mt19937(3));

    for ($step = 0; $step < 60; $step++) {
        $grid = randomGrid($random, $random->getInt(3, 40), $random->getInt(3, 40));
        $camera = randomCamera($random, $grid);
        $columns = $random->getInt(1, 400);
        $reach = $random->getInt(0, 3) === 0 ? $random->getFloat(0.1, 30.0) : INF;

        $native = (new PHPLandRaycastingDriver())->caster($grid)->fan($camera, $columns, $reach);
        $ext = (new CLandRaycastingDriver())->caster($grid)->fan($camera, $columns, $reach);

        expect(bin2hex($ext))->toBe(bin2hex($native), "fan {$step}")
            ->and(Hits::count($ext))->toBe($columns);
    }
});

it('pictures the same bytes', function (): void {
    $random = new Randomizer(new Mt19937(4));

    for ($step = 0; $step < 40; $step++) {
        $grid = randomGrid($random, $random->getInt(3, 30), $random->getInt(3, 30));
        // Half the cameras stand in an open cell, so walls are seen; the rest stand anywhere.
        $camera = randomCamera($random, $grid);
        if ($step % 2 === 0) {
            $camera = $camera->at(1.0 + $random->getFloat(0.0, $grid->width - 2.0), 1.0 + $random->getFloat(0.0, $grid->height - 2.0));
        }
        $scenery = randomScenery($random);
        [$width, $height] = [$random->getInt(1, 96), $random->getInt(1, 64)];
        $reach = $random->getInt(0, 3) === 0 ? $random->getFloat(0.1, 30.0) : INF;

        $native = (new PHPLandRaycastingDriver())->caster($grid)->view($camera, $width, $height, $scenery, $reach);
        $ext = (new CLandRaycastingDriver())->caster($grid)->view($camera, $width, $height, $scenery, $reach);

        expect(strlen($native))->toBe($width * $height * 4)
            ->and(bin2hex($ext))->toBe(bin2hex($native), "view {$step}: {$width}x{$height}");
    }
});

it('pictures a wall the eye is against, and one a long way off, to the same bytes', function (): void {
    $grid = Grid::of([[1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2]]);
    $scenery = new Scenery([1 => 0xFF0000FF, 2 => new Texture(random_bytes(7 * 5 * 4), 7, 5)], 0x111111FF, 0x222222FF, 90);

    foreach ([1.0, 1.0000001, 1.5, 14.999999, 15.0 - 1e-12] as $x) {
        foreach ([0.0, M_PI] as $angle) {
            $camera = new Camera($x, 0.5, $angle);
            $native = (new PHPLandRaycastingDriver())->caster($grid)->view($camera, 33, 21, $scenery);
            $ext = (new CLandRaycastingDriver())->caster($grid)->view($camera, 33, 21, $scenery);

            expect(bin2hex($ext))->toBe(bin2hex($native), "x {$x} at {$angle}");
        }
    }
});
