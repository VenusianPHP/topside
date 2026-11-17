<?php

namespace Surface\Contracts\Graphics;

use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Hit;
use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Contracts\Rendering\Raycasting\Scenery;

/**
 * Casts rays through a grid of walls, and paints what a camera sees of it onto one of its engine's
 * surfaces. An engine that casts rays (CastsRays) makes one for a grid.
 */
interface Raycaster
{
    /** The world it casts into. */
    public function grid(): Grid;

    /**
     * One ray from ($x, $y) at $angle (radians, 0 east, growing toward south): what it hits within
     * $reach cells, or null.
     *
     * @throws RaycastException When a number is not finite, the position is out of range, or the reach is not above 0.
     */
    public function cast(float $x, float $y, float $angle, float $reach = INF): ?Hit;

    /**
     * Many rays from one point, one an angle, in one call: what each hits, in the order of
     * $angles, null for a miss.
     *
     * @param list<float> $angles
     * @return list<Hit|null>
     *
     * @throws RaycastException As cast() refuses a ray.
     */
    public function casts(float $x, float $y, array $angles, float $reach = INF): array;

    /**
     * One ray a column across the camera's field of view, left to right, as Hits bytes.
     *
     * @throws RaycastException When $columns is out of range, or the reach is not above 0.
     */
    public function fan(Camera $camera, int $columns, float $reach = INF): string;

    /**
     * What $camera sees, painted over $where on the surface. It is a frame of its own: the surface
     * draws its next render() whole.
     *
     * @param Region|null $where null: the whole surface
     *
     * @throws GraphicsException When $onto is another engine's.
     * @throws RaycastException When $where is not inside the surface.
     */
    public function paint(Camera $camera, Scenery $scenery, DrawingSurface $onto, ?Region $where = null, float $reach = INF): static;
}
