<?php

namespace VelvetGE\Rasterization;

use Surface\Contracts\Rendering\deprecated\Rasterization\BatchScanner;
use Surface\Contracts\Rendering\deprecated\Rasterization\FillRule;
use Surface\Contracts\Rendering\deprecated\Rasterization\RasterizeException;
use Surface\Contracts\Rendering\deprecated\Rasterization\Scanner;

/**
 * Scanner ops answered by the scanner they were lowered for. A rasterizer only lowers a shape to
 * an op its scanner has, so every op here is one the scanner answers itself.
 */
final class Ops
{
    /**
     * One span string an op, in order: one call when the scanner batches, op by op otherwise.
     *
     * @param  list<list<mixed>>  $ops
     * @return list<string>
     */
    public static function scan(Scanner $scanner, array $ops): array
    {
        if ($scanner instanceof BatchScanner) {
            return $scanner->batch($ops);
        }

        return array_map(fn (mixed $op): string => match (is_array($op) ? ($op[0] ?? null) : null) {
            Scanner::PATH => $scanner->path($op[1], $op[2] === 1 ? FillRule::EVEN_ODD : FillRule::NON_ZERO),
            Scanner::ELLIPSE => $scanner->ellipse($op[1], $op[2], $op[3], $op[4]),
            Scanner::RING => $scanner->ring($op[1], $op[2], $op[3], $op[4], $op[5]),
            Scanner::ROUND_RECT => $scanner->roundRect($op[1], $op[2], $op[3], $op[4], $op[5]),
            Scanner::ROUND_RING => $scanner->roundRing($op[1], $op[2], $op[3], $op[4], $op[5], $op[6]),
            Scanner::SHADOW => $scanner->shadow($op[1], $op[2], $op[3], $op[4], $op[5], $op[6]),
            Scanner::POLYLINE => $scanner->polyline($op[1], $op[2]),
            default => throw new RasterizeException('An op is not a list that starts with a Scanner op.'),
        }, $ops);
    }
}
