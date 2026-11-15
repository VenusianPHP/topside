<?php

namespace Surface\Rendering;

use Surface\Contracts\Rendering\Rasterization\DashGeometry;
use Surface\Contracts\Rendering\Rasterization\StrokeCap;
use Surface\Contracts\Rendering\Rasterization\StrokeGeometry;
use Surface\Contracts\Rendering\Rasterization\StrokeJoin;

/**
 * What the recorder, the rasterizers and the GPU engines call to stroke a polyline: the stroker of the
 * stroking driver in use (rendering.stroker.default), or one given with use(). Topside brings no stroker
 * of its own: a package registers one on app('strokers').
 */
final class Stroker
{
    public const float MITER_LIMIT = StrokeGeometry::MITER_LIMIT;

    private static ?StrokeGeometry $using = null;

    /** Stroke with $geometry from now on; null goes back to the stroking driver in use. */
    public static function use(?StrokeGeometry $geometry): void
    {
        self::$using = $geometry;
    }

    /** What is in use. */
    public static function geometry(): StrokeGeometry
    {
        return self::$using ?? Strokers::driver()->stroker();
    }

    /**
     * @param  list<array{float, float}>  $points
     * @param  array{float, float}|null  $direction  The way a zero-length stroke runs, for the orientation of a square cap; none: along x.
     * @return list<list<float>> Flat x, y, x, y… contours.
     */
    public static function outline(array $points, float $stroke, bool $closed, StrokeCap $cap = StrokeCap::BUTT, StrokeJoin $join = StrokeJoin::MITER, float $miterLimit = self::MITER_LIMIT, ?array $direction = null): array
    {
        return self::geometry()->outline($points, $stroke, $closed, $cap, $join, $miterLimit, $direction);
    }

    /**
     * The dash pieces of $points, or null when the stroker in use cannot cut dashes: the caller hands
     * that stroke back undrawn.
     *
     * @param  list<array{float, float}>  $points
     * @param  list<float>  $dash  Lengths, not negative, with a positive sum.
     * @return list<array{points: list<array{float, float}>, closed: bool, direction: array{float, float}}>|null
     */
    public static function dashed(array $points, bool $closed, array $dash, float $offset): ?array
    {
        $geometry = self::geometry();

        return $geometry instanceof DashGeometry ? $geometry->dashed($points, $closed, $dash, $offset) : null;
    }
}
