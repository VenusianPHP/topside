<?php

namespace Surface\Graphics;

use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Hit;
use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Contracts\Rendering\Raycasting\Scenery;

/** Casts rays through a grid, and paints a view of it into one of its engine's framebuffers. Each engine has its own. */
abstract class GERaycaster extends GEResource
{
    /** The world it casts into: set when the engine made this raycaster. */
    abstract public function grid(): Grid;

    /**
     * One ray from ($x, $y) at $angle (radians, 0 east, growing toward south): what it hits within $reach cells,
     * or null. A line of sight, where a light stops, what is under a pointer on a map.
     *
     * @throws RaycastException When a number is not finite, the position is out of range, or the reach is not above 0.
     */
    abstract public function cast(float $x, float $y, float $angle, float $reach = INF): ?Hit;

    /**
     * One ray a column across the camera's field of view, left to right, as Hits bytes: read them with Hits.
     * A column's distance is measured along the camera's direction, so a flat wall has one distance.
     *
     * @throws RaycastException When $columns is out of range, or the reach is not above 0.
     */
    abstract public function fan(Camera $camera, int $columns, float $reach = INF): string;

    /**
     * Many rays from one point, one an angle, in one call: what each hits, in the order of $angles, null for a
     * miss. An engine whose rays run far from the caller (a GPU's) casts them all in one trip; this one casts
     * them one at a time.
     *
     * @param list<float> $angles
     * @return list<Hit|null>
     *
     * @throws RaycastException As cast() refuses a ray.
     */
    public function casts(float $x, float $y, array $angles, float $reach = INF): array
    {
        return array_map(fn (float $angle): ?Hit => $this->cast($x, $y, $angle, $reach), $angles);
    }

    abstract protected function paintView(Camera $camera, Scenery $scenery, GEFramebuffer $onto, Region $where, float $reach): void;

    /**
     * What $camera sees, over $where.
     *
     * @param Region|null $where null: the whole surface
     * @param float $reach how far a ray goes before it counts as hitting nothing
     *
     * @throws GraphicsException When $onto is another engine's, or $where is not inside it.
     */
    public function paint(Camera $camera, Scenery $scenery, GEFramebuffer $onto, ?Region $where = null, float $reach = INF): static
    {
        $this->mine($onto);
        $whole = Region::wholeSurface($onto->width(), $onto->height());
        $where ??= $whole;
        if ($where->isEmpty() || $where->x < 0 || $where->y < 0 || $where->right() > $whole->width || $where->bottom() > $whole->height) {
            throw GraphicsException::outsideSurface($where, $whole->width, $whole->height);
        }
        $this->paintView($camera, $scenery, $onto, $where, $reach);

        return $this;
    }
}
