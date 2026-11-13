<?php

namespace Surface\Contracts\Rendering\Rasterization;

/**
 * What turns a polyline into a stroke: the contours that, filled with the non-zero rule, are its outline, and the
 * pieces a dash pattern cuts it into. Plain geometry, in framebuffer pixels: no pixel, no colour, no frame.
 *
 * Every implementation answers the numbers of Surface\Rendering\Rasterization\PHPStroker, to the last bit: a quad
 * a segment; at each corner the join (a miter, cut to a bevel where its tip would sit more than the miter limit of
 * half-strokes from the corner; a bevel; or a round arc); at the ends of an open polyline the cap (butt, square or
 * round); every contour turned to positive signed area, one with none dropped. A round join or cap strays from its
 * arc by less than a tenth of a pixel.
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

    /**
     * $points cut into the pieces a dash pattern leaves: on, off, on, off… (an odd count is repeated), starting $offset
     * into it. A piece that runs round a corner keeps the corner. An on element of length 0 leaves a zero-length piece:
     * a dot under a round cap. A closed polyline is walked once round; a dash that runs across its start is one piece,
     * and a dash that never stops is the whole polyline, closed. A pattern with nothing longer than 1e-12 in it is nothing.
     *
     * @param  list<array{float, float}>  $points
     * @param  list<float>  $dash  Lengths, finite and not negative.
     * @return list<array{points: list<array{float, float}>, closed: bool, direction: array{float, float}}> direction: the way the piece runs where it ends, for a zero-length one's square cap
     */
    public function dashed(array $points, bool $closed, array $dash, float $offset): array;
}
