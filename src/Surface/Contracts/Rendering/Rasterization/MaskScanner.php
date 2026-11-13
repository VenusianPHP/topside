<?php

namespace Surface\Contracts\Rendering\Rasterization;

/**
 * Optional: a scanner that answers a shape's coverage as a block of bytes, one a pixel, for a
 * library that draws into a surface and has no span list to give. The engine hands the block to
 * the pixel store, which blends it natively, so nothing is run-length read in PHP.
 */
interface MaskScanner extends Scanner
{
    /**
     * The coverage of one op this scanner itself has, inside the clip: whole rows of $width bytes
     * 0..255, top row first, the first byte the pixel at ($left, $top). Null when it covers nothing.
     *
     * @param  list<mixed>  $op  as BatchScanner::batch() takes one
     * @return array{string, int, int, int}|null  [coverage, width, left, top], as Framebuffer::paintMasks() takes one
     */
    public function mask(array $op): ?array;
}
