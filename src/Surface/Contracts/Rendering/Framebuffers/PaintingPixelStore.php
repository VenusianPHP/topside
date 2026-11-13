<?php

namespace Surface\Contracts\Rendering\Framebuffers;

use Surface\NutsAndBolts\GradientSpread;

/**
 * A store that paints: coverage, masks, gradients and placed images blended
 * over what it holds. Optional. A store that leaves it out is painted by
 * VelvetGE's own arithmetic over its pixels.
 */
interface PaintingPixelStore extends PixelStore
{
    /**
     * Paint coverage spans (Spans) in one 0xRRGGBBAA colour, by Framebuffer::paintSpans()'s rules.
     *
     * @return Region|null The spans' bounding box; null for an empty list.
     */
    public function paintSpans(string $spans, int $rgba8): ?Region;

    /**
     * Paint coverage masks in one 0xRRGGBBAA colour, by Framebuffer::paintMasks()'s rules.
     *
     * @param  list<array{string, int, int, int}>  $masks  [coverage, width, left, top]
     * @return Region|null The box of the pixels with coverage (the box their spans would have); null for none.
     */
    public function paintMasks(array $masks, int $rgba8, Region $clip): ?Region;

    /**
     * Framebuffer::paintGradient() on this store; spans in this store's own rows.
     *
     * @param  int  $row  The surface row this store's row 0 stands for (as in paintRgba8()).
     * @return Region|null The spans' bounding box; null for an empty list.
     */
    public function paintGradient(string $spans, string $ramp, string $kind, GradientSpread $spread, array $inverse, int $row = 0): ?Region;

    /**
     * Paint RGBA8 pixels through an inverse placement: for each pixel of $target,
     * a rect inside the store, the point (u, v) = $inverse × (x + ½, y + $row + ½)
     * picks the source pixel when it lies inside the $width × $height image; see
     * Framebuffer::paintImage() for the sampling and the blend.
     *
     * @param  array{float, float, float, float, float, float}  $inverse  a, b, c, d, e, f: u = a·x + c·y + e, v = b·x + d·y + f
     * @param  int  $row  The surface row this store's row 0 stands for: a paged buffer's window sits $row rows down its surface.
     * @param  int  $tint  0xRRGGBBAA, multiplied into each source pixel before the blend; see Framebuffer::paintImage().
     * @param  array{float, float, float, float}|null  $window  [u0, v0, u1, v1] with u0 < u1 and v0 < v1, in the image's own pixels: the part that paints. Null: all of it.
     * @param  string|null  $coverage  Spans in this store's own rows (the rows of $target): the alpha of a pixel is scaled by the coverage of the span that holds it, and a pixel no span holds is not painted. Null: no mask.
     */
    public function paintRgba8(string $rgba8, int $width, int $height, array $inverse, Region $target, int $opacity, Filter $filter, int $row = 0, int $tint = 0xFFFFFFFF, ?array $window = null, ?string $coverage = null): void;
}
