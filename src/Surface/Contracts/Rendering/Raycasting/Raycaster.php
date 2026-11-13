<?php

namespace Surface\Contracts\Rendering\Raycasting;

use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;

/**
 * Rays through one Grid. A ray walks from cell to cell and stops at the first
 * wall it enters: the cell it starts in is not looked at, and a ray that
 * starts outside the grid, leaves it, or goes past its reach hits nothing.
 *
 * Every driver answers the same bytes for the same call.
 */
interface Raycaster
{
    /** A position past it, either way, is refused. */
    public const int LIMIT = 1073741824;

    /** The most pixels a view's side holds. */
    public const int MAX_VIEW = 16384;

    /** The name of the driver that made it. */
    public function driver(): string;

    public function grid(): Grid;

    /**
     * One ray from ($x, $y) at $angle (radians, 0 east, growing toward south): what it hits within $reach cells,
     * or null. A line of sight, a shot, what is under a pointer on a map.
     *
     * @throws RaycastException When a number is not finite, the position is past LIMIT, or the reach is not above 0.
     */
    public function cast(float $x, float $y, float $angle, float $reach = INF): ?Hit;

    /**
     * One ray a column across the camera's field of view, left to right, as Hits bytes: $columns records.
     * A column's distance is measured along the camera's direction, so a flat wall has one distance.
     *
     * @param int $columns 1..Grid::MAX_SIDE
     * @throws RaycastException When $columns is out of range, or the reach is not above 0.
     */
    public function fan(Camera $camera, int $columns, float $reach = INF): string;

    /**
     * What the camera sees as RGBA8 pixels, $width x $height, top row first: the scenery's ceiling above the
     * middle row, its floor from it down, and in each column that hits a wall a line of that wall,
     * (int) ($height / distance) pixels tall, centred. A texture is sampled nearest.
     *
     * The bytes go into any framebuffer with Framebuffer::writeRgba8($pixels, $width, $height, $x, $y).
     *
     * @param int $width 1..MAX_VIEW
     * @param int $height 1..MAX_VIEW
     * @throws RaycastException When a side is out of range, the reach is not above 0, or a ray hits a wall the scenery has no entry for.
     */
    public function view(Camera $camera, int $width, int $height, Scenery $scenery, float $reach = INF): string;

    /**
     * view()'s picture put on a framebuffer: over the whole surface, or over $where, which must be inside it.
     * Each pixel replaces the one there, mapped into the framebuffer's format, and the framebuffer records the
     * rect as written. A raycaster may draw straight into the framebuffer's memory when it can, the same pixels.
     *
     * @throws RaycastException As view(), and when $where is empty or not inside the framebuffer; nothing is then written.
     */
    public function paint(Camera $camera, Scenery $scenery, Framebuffer $target, ?Region $where = null, float $reach = INF): void;
}
