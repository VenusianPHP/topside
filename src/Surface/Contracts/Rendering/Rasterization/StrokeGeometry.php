<?php

namespace Surface\Contracts\Rendering\Rasterization;

/**
 * What turns a polyline into a stroke: the contours that, filled with the non-zero rule, are its
 * outline. Plain geometry, in framebuffer pixels: no pixel, no colour, no frame.
 *
 * A stroking driver hands one out (Surface\Rendering\Stroking\StrokingDriver), and each answers with
 * its own library's stroker: a quad a segment, at each corner the join (a miter, cut to a bevel where
 * its tip would sit more than the miter limit of half-strokes from the corner; a bevel; or a round
 * arc), at the ends of an open polyline the cap (butt, square or round). How a round join or cap is
 * flattened, and how its contours wind, is the library's. Cutting a stroke into dashes is optional:
 * DashGeometry.
 */
interface StrokeGeometry
{
    public const float MITER_LIMIT = 4.0;

    /**
     * The stroke of $points, $stroke wide: nothing when the stroke is not above 0.
     *
     * @param  list<array{float, float}>  $points
     * @param  array{float, float}|null  $direction  The way a zero-length stroke runs, for the orientation of a square cap; none: along x.
     * @return list<list<float>> Flat x, y, x, y… contours.
     */
    public function outline(array $points, float $stroke, bool $closed, StrokeCap $cap = StrokeCap::BUTT, StrokeJoin $join = StrokeJoin::MITER, float $miterLimit = self::MITER_LIMIT, ?array $direction = null): array;
}
