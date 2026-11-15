<?php

namespace Surface\Contracts\Rendering\Rasterization;

/**
 * Optional: a stroker that also cuts a polyline into the pieces a dash pattern leaves. A stroker
 * whose library has no dashing does not implement it, and a dashed stroke handed to it comes back
 * undrawn.
 */
interface DashGeometry extends StrokeGeometry
{
    /**
     * $points cut into the pieces a dash pattern leaves: on, off, on, off… (an odd count is repeated),
     * starting $offset into it. A piece that runs round a corner keeps the corner. An on element of
     * length 0 leaves a zero-length piece: a dot under a round cap. A closed polyline is walked once
     * round; a dash that runs across its start is one piece, and a dash that never stops is the whole
     * polyline, closed. A pattern with nothing longer than 1e-12 in it is nothing.
     *
     * @param  list<array{float, float}>  $points
     * @param  list<float>  $dash  Lengths, finite and not negative.
     * @return list<array{points: list<array{float, float}>, closed: bool, direction: array{float, float}}> direction: the way the piece runs where it ends, for a zero-length one's square cap
     */
    public function dashed(array $points, bool $closed, array $dash, float $offset): array;
}
