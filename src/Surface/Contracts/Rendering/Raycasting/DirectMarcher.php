<?php

namespace Surface\Contracts\Rendering\Raycasting;

use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Framebuffers\Region;

/**
 * Optional: a marcher that draws a view straight into a pixel store's memory, with no picture
 * built in between. It says which memory it can draw into, by what the memory is (its format,
 * its row length), never by which library holds it. The engine asks drawsInto() first and calls
 * pictureInto() only after a true; a false sends the view through picture() instead.
 */
interface DirectMarcher extends Marcher
{
    /** Whether this marcher can draw into $memory as it is. */
    public function drawsInto(PixelMemory $memory): bool;

    /**
     * picture()'s pixels written into $memory over $where, a rect inside it, each replacing the
     * pixel there. The view is $where's size. Everything picture() refuses is refused before a
     * byte is written.
     *
     * @param array<int, int|array{string, int, int}> $walls Scenery::table()
     * @throws RaycastException When a ray hits a wall with no entry in $walls; nothing is then written.
     */
    public function pictureInto(PixelMemory $memory, Region $where, float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $width, int $height, array $walls, int $ceiling, int $floor, int $shade, float $reach): void;
}
