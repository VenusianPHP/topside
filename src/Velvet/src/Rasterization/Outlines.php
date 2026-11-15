<?php

namespace VelvetGE\Rasterization;

use Surface\NutsAndBolts\Path;

/**
 * Curved shapes as outlines: what a rasterizer hands a scanner that fills paths and has no shape
 * formulas of its own. It is geometry only. The scanner that fills the outline still decides every
 * pixel of coverage.
 *
 * Each method answers [contours, rule]: flat x, y lists, closed implicitly, and 0 for non-zero or
 * 1 for even-odd, as a Scanner::PATH op carries them. Curves are cubic arcs flattened to within
 * TOLERANCE of the true curve.
 */
final class Outlines
{
    /** How far a flattened curve may stray from the arc, in pixels. */
    public const float TOLERANCE = 0.05;

    /** A cubic's control distance for a quarter circle of radius 1. */
    private const float KAPPA = 0.5522847498307936;

    /** @return array{list<list<float>>, int} */
    public static function ellipse(float $cx, float $cy, float $rx, float $ry): array
    {
        return [self::flat(self::addEllipse(new Path(), $cx, $cy, $rx, $ry)), 0];
    }

    /**
     * The band between radii r ± stroke / 2; all of the outer ellipse when the inner one vanishes.
     *
     * @return array{list<list<float>>, int}
     */
    public static function ring(float $cx, float $cy, float $rx, float $ry, float $stroke): array
    {
        $half = $stroke / 2;
        $path = self::addEllipse(new Path(), $cx, $cy, $rx + $half, $ry + $half);
        if ($rx - $half > 0 && $ry - $half > 0) {
            self::addEllipse($path, $cx, $cy, $rx - $half, $ry - $half);
        }

        return [self::flat($path), 1];
    }

    /**
     * @param  array{float, float, float, float}  $radii  [tl, tr, br, bl], already held to the box
     * @return array{list<list<float>>, int}
     */
    public static function roundRect(float $x0, float $y0, float $x1, float $y1, array $radii): array
    {
        return [self::flat(self::addRoundRect(new Path(), $x0, $y0, $x1, $y1, $radii)), 0];
    }

    /**
     * The band inside the box, $inset wide: the inner box's radii are max(0, r − inset). The whole
     * box when the band reaches the middle.
     *
     * @param  array{float, float, float, float}  $radii
     * @return array{list<list<float>>, int}
     */
    public static function roundRing(float $x0, float $y0, float $x1, float $y1, array $radii, float $inset): array
    {
        $path = self::addRoundRect(new Path(), $x0, $y0, $x1, $y1, $radii);
        if ($x1 - $inset > $x0 + $inset && $y1 - $inset > $y0 + $inset) {
            self::addRoundRect($path, $x0 + $inset, $y0 + $inset, $x1 - $inset, $y1 - $inset, array_map(fn (float $r): float => max(0.0, $r - $inset), $radii));
        }

        return [self::flat($path), 1];
    }

    private static function addEllipse(Path $path, float $cx, float $cy, float $rx, float $ry): Path
    {
        [$kx, $ky] = [$rx * self::KAPPA, $ry * self::KAPPA];

        return $path->moveTo($cx + $rx, $cy)
            ->cubicTo($cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry)
            ->cubicTo($cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy)
            ->cubicTo($cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry)
            ->cubicTo($cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy)
            ->close();
    }

    /** @param array{float, float, float, float} $radii */
    private static function addRoundRect(Path $path, float $x0, float $y0, float $x1, float $y1, array $radii): Path
    {
        [$tl, $tr, $br, $bl] = array_values($radii);
        $k = 1 - self::KAPPA;
        $path->moveTo($x0 + $tl, $y0)->lineTo($x1 - $tr, $y0);
        if ($tr > 0) {
            $path->cubicTo($x1 - $tr * $k, $y0, $x1, $y0 + $tr * $k, $x1, $y0 + $tr);
        }
        $path->lineTo($x1, $y1 - $br);
        if ($br > 0) {
            $path->cubicTo($x1, $y1 - $br * $k, $x1 - $br * $k, $y1, $x1 - $br, $y1);
        }
        $path->lineTo($x0 + $bl, $y1);
        if ($bl > 0) {
            $path->cubicTo($x0 + $bl * $k, $y1, $x0, $y1 - $bl * $k, $x0, $y1 - $bl);
        }
        $path->lineTo($x0, $y0 + $tl);
        if ($tl > 0) {
            $path->cubicTo($x0, $y0 + $tl * $k, $x0 + $tl * $k, $y0, $x0 + $tl, $y0);
        }

        return $path->close();
    }

    /** @return list<list<float>> */
    private static function flat(Path $path): array
    {
        $contours = [];
        foreach ($path->flatten(self::TOLERANCE) as $subpath) {
            $flat = [];
            foreach ($subpath['points'] as [$x, $y]) {
                $flat[] = $x;
                $flat[] = $y;
            }
            if (count($flat) >= 6) {
                $contours[] = $flat;
            }
        }

        return $contours;
    }
}
