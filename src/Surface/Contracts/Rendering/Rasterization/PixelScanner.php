<?php

namespace Surface\Contracts\Rendering\Rasterization;

/**
 * Optional: work defined pixel by pixel, which a geometry library has no call
 * for. One-pixel lines through exact pixels, and a shadow whose coverage is a
 * formula at each pixel centre. A scanner without this tier has them scanned
 * by the engine's own.
 */
interface PixelScanner extends Scanner
{
    /**
     * Coverage 1 − smoothstep(−blur, blur, d) at each pixel centre, d the signed distance to the box
     * rounded by $radius; hard edges keep coverage ≥ 128 at 255. A blur of 0 is the hard box.
     */
    public function shadow(float $x0, float $y0, float $x1, float $y1, float $radius, float $blur): string;

    /**
     * One-pixel lines between the floored points, every pixel once, coverage 255 whatever the edges.
     *
     * @param  list<float>  $points  A flat x, y, x, y… list.
     */
    public function polyline(array $points, bool $closed): string;
}
