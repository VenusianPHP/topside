<?php

namespace Surface\Contracts\Rendering\Raycasting;

/**
 * The grid walk a raycasting driver supplies, over one grid: rays answered as Hits records and a
 * view answered as RGBA8 bytes. Every argument arrives checked, as vectors, never angles. The
 * engine's raycaster does the checking, the camera's arithmetic and the way a view reaches a
 * framebuffer, so a library binding is only this.
 *
 * A marcher that can also draw a view straight into a pixel store's memory is a DirectMarcher.
 */
interface Marcher
{
    /** One ray from ($x, $y) along ($dx, $dy), a unit vector: one Hits record. */
    public function ray(float $x, float $y, float $dx, float $dy, float $reach): string;

    /**
     * One ray a column, left to right: $columns Hits records. Column i looks along
     * dir + plane * ((2i + 1) / columns - 1).
     */
    public function rays(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $columns, float $reach): string;

    /**
     * What those rays see as RGBA8, $width x $height, top row first.
     *
     * @param array<int, int|array{string, int, int}> $walls Scenery::table()
     * @throws RaycastException When a ray hits a wall with no entry in $walls.
     */
    public function picture(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $width, int $height, array $walls, int $ceiling, int $floor, int $shade, float $reach): string;
}
