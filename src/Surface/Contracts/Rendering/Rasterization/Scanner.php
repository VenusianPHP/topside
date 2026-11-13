<?php

namespace Surface\Contracts\Rendering\Rasterization;

use Surface\Contracts\Rendering\Framebuffers\Region;

/**
 * The row work a rasterization driver supplies: shapes already lowered by the
 * Rasterizer, answered as span bytes (see
 * Surface\Contracts\Rendering\Framebuffers\Spans) inside the clip.
 *
 * This is the core every scanner has: filling a path. More is optional, each
 * its own interface. Three add ops: ShapeScanner (curves by formula),
 * PixelScanner (work defined pixel by pixel) and BatchScanner (many ops in
 * one call); a scanner that leaves one out is completed with the engine's
 * own, so a rasterizer always has every op. Two add ways onto a framebuffer:
 * DirectScanner (blend straight into a pixel store's memory) and MaskScanner
 * (coverage as bytes, for a library with no span list).
 *
 * The engine's own scanners answer the same bytes for the same input. A library
 * binding answers its own coverage: the same shape, with its own arithmetic
 * at the edges.
 *
 * Coordinates are finite with magnitude at most LIMIT; anything else throws
 * RasterizeException.
 */
interface Scanner
{
    /** 2³⁰: past it, the one-pixel line arithmetic would leave 64-bit integers. */
    public const float LIMIT = 1073741824.0;

    /** The ops batch() takes, numbered as ext-rasterize's RASTER_OP_*. */
    public const int PATH = 1;

    public const int ELLIPSE = 2;

    public const int RING = 3;

    public const int ROUND_RECT = 4;

    public const int ROUND_RING = 5;

    public const int SHADOW = 6;

    public const int POLYLINE = 7;

    public function clip(): Region;

    public function edges(): Edges;

    /**
     * @param  list<list<float>>  $contours  Each a flat x, y, x, y… list, closed implicitly.
     */
    public function path(array $contours, FillRule $rule): string;
}
