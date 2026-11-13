<?php

namespace Surface\Rendering\Rasterization;

use Surface\Contracts\Rendering\Rasterization\StrokeCap;
use Surface\Contracts\Rendering\Rasterization\StrokeGeometry;
use Surface\Contracts\Rendering\Rasterization\StrokeJoin;

/**
 * What the recorder, the rasterizer and the GPU lowering call to stroke a polyline: StrokeGeometry's
 * two methods, answered by the implementation in use. That is PHPStroker, which needs nothing, until
 * something brings a faster one with use(): VelvetGE does, in C, when ext-rasterize is loaded. Every
 * implementation answers the same numbers as PHPStroker.
 */
final class Stroker
{
    public const float MITER_LIMIT = StrokeGeometry::MITER_LIMIT;

    private static ?StrokeGeometry $using = null;

    /** Stroke with $geometry from now on; null goes back to PHPStroker. */
    public static function use(?StrokeGeometry $geometry): void
    {
        self::$using = $geometry;
    }

    /** What is in use. */
    public static function geometry(): StrokeGeometry
    {
        return self::$using ??= new PHPStroker();
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
     * @param  list<array{float, float}>  $points
     * @param  list<float>  $dash  Lengths, not negative, with a positive sum.
     * @return list<array{points: list<array{float, float}>, closed: bool, direction: array{float, float}}>
     */
    public static function dashed(array $points, bool $closed, array $dash, float $offset): array
    {
        return self::geometry()->dashed($points, $closed, $dash, $offset);
    }
}
