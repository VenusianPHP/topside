<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Grid;

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
