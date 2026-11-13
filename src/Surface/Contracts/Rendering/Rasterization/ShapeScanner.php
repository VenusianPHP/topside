<?php

namespace Surface\Contracts\Rendering\Rasterization;

/**
 * Optional: curved shapes answered by formula, not as a flattened path. A
 * scanner without this tier has them scanned by the engine's own.
 */
interface ShapeScanner extends Scanner
{
    public function ellipse(float $cx, float $cy, float $rx, float $ry): string;

    /** The band between radii r + stroke / 2 and r - stroke / 2. */
    public function ring(float $cx, float $cy, float $rx, float $ry, float $stroke): string;

    /**
     * A box [x0, x1) × [y0, y1) with each corner cut by a quarter circle.
     *
     * @param  array{float, float, float, float}  $radii  [tl, tr, br, bl], already held to 0..min(w, h)/2
     */
    public function roundRect(float $x0, float $y0, float $x1, float $y1, array $radii): string;

    /**
     * The band between the box and the box inset by $inset, whose radii are max(0, r − inset);
     * the whole box when the inset box is empty.
     *
     * @param  array{float, float, float, float}  $radii  [tl, tr, br, bl], already held
     */
    public function roundRing(float $x0, float $y0, float $x1, float $y1, array $radii, float $inset): string;
}
