<?php

namespace Surface\Rendering\Raycasting;

use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Hit;
use Surface\Contracts\Rendering\Raycasting\Hits;
use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Contracts\Rendering\Raycasting\Raycaster as RaycasterContract;
use Surface\Contracts\Rendering\Raycasting\Scenery;

/**
 * The Raycaster contract over three operations on plain numbers. Everything is checked here and
 * the angles become vectors here, so every driver is handed the same numbers and refuses the
 * same calls; a driver only walks the grid.
 */
abstract class Raycaster implements RaycasterContract
{
    public function __construct(
        protected readonly Grid $grid,
    ) {}

    public function grid(): Grid
    {
        return $this->grid;
    }

    public function cast(float $x, float $y, float $angle, float $reach = INF): ?Hit
    {
        foreach (['x' => $x, 'y' => $y] as $what => $value) {
            if (! is_finite($value)) {
                throw RaycastException::notFinite("A ray's {$what}");
            }
            if (abs($value) > self::LIMIT) {
                throw RaycastException::pastLimit("A ray's {$what}", $value);
            }
        }
        if (! is_finite($angle)) {
            throw RaycastException::notFinite("A ray's angle");
        }
        self::checkReach($reach);

        return Hits::at($this->ray($x, $y, cos($angle), sin($angle), $reach), 0);
    }

    public function fan(Camera $camera, int $columns, float $reach = INF): string
    {
        if ($columns < 1 || $columns > Grid::MAX_SIDE) {
            throw RaycastException::side("A fan's columns", $columns, Grid::MAX_SIDE);
        }
        self::checkReach($reach);

        return $this->rays($camera->x, $camera->y, ...$camera->direction(), ...$camera->plane(), ...[$columns, $reach]);
    }

    public function view(Camera $camera, int $width, int $height, Scenery $scenery, float $reach = INF): string
    {
        return $this->picture(...self::pictureOf($camera, $width, $height, $scenery, $reach));
    }

    /** Through view() and Framebuffer::writeRgba8(), which every framebuffer has. A driver that can draw in place overrides this. */
    public function paint(Camera $camera, Scenery $scenery, Framebuffer $target, ?Region $where = null, float $reach = INF): void
    {
        $where = self::inside($target, $where);

        $target->writeRgba8($this->view($camera, $where->width, $where->height, $scenery, $reach), $where->width, $where->height, $where->x, $where->y);
    }

    /**
     * Where a view goes on a framebuffer: $where, or the whole surface.
     *
     * @throws RaycastException When $where is empty or not inside the framebuffer.
     */
    protected static function inside(Framebuffer $target, ?Region $where): Region
    {
        [$width, $height] = [$target->viewportWidth(), $target->viewportHeight()];
        $where ??= Region::wholeSurface($width, $height);
        if ($where->isEmpty() || $where->x < 0 || $where->y < 0 || $where->right() > $width || $where->bottom() > $height) {
            throw RaycastException::outside($where->x, $where->y, $where->width, $where->height, $width, $height);
        }

        return $where;
    }

    /**
     * A view's arguments, checked, as picture() takes them.
     *
     * @return array{float, float, float, float, float, float, int, int, array, int, int, int, float}
     */
    protected static function pictureOf(Camera $camera, int $width, int $height, Scenery $scenery, float $reach): array
    {
        if ($width < 1 || $width > self::MAX_VIEW) {
            throw RaycastException::side("A view's width", $width, self::MAX_VIEW);
        }
        if ($height < 1 || $height > self::MAX_VIEW) {
            throw RaycastException::side("A view's height", $height, self::MAX_VIEW);
        }
        self::checkReach($reach);

        return [$camera->x, $camera->y, ...$camera->direction(), ...$camera->plane(), ...[$width, $height, $scenery->table(), $scenery->ceiling, $scenery->floor, $scenery->shade, $reach]];
    }

    /**
     * One ray from ($x, $y) along ($dx, $dy), a unit vector: one Hits record.
     */
    abstract protected function ray(float $x, float $y, float $dx, float $dy, float $reach): string;

    /**
     * One ray a column, left to right: $columns Hits records. Column i looks along
     * dir + plane * ((2i + 1) / columns - 1).
     */
    abstract protected function rays(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $columns, float $reach): string;

    /**
     * What those rays see as RGBA8, $width x $height, top row first.
     *
     * @param array<int, int|array{string, int, int}> $walls Scenery::table()
     * @throws RaycastException When a ray hits a wall with no entry in $walls.
     */
    abstract protected function picture(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $width, int $height, array $walls, int $ceiling, int $floor, int $shade, float $reach): string;

    /** @throws RaycastException Unless $reach is above 0; INF is. */
    private static function checkReach(float $reach): void
    {
        if (! ($reach > 0.0)) {
            throw RaycastException::reach($reach);
        }
    }
}
