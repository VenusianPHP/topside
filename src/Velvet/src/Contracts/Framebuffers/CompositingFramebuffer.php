<?php

namespace VelvetGE\Contracts\Framebuffers;

use Surface\Contracts\Rendering\deprecated\Framebuffers\Filter;
use Surface\Contracts\Rendering\deprecated\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Region;
use Surface\NutsAndBolts\Affine;

/**
 * A framebuffer that blends into its own pixels: coverage and a colour, or another framebuffer, composited
 * source-over. Gradients are their own contract, GradientFramebuffer. The painter decides what is drawn; this is where the decided pixels land.
 */
interface CompositingFramebuffer extends InstanceThatManagesAFramebuffer
{
    /**
     * Paint coverage spans (Spans) in one 0xRRGGBBAA colour: RGB and grey
     * formats blend source-over by alpha × coverage; mono, palette and planar
     * formats write the colour where that reaches 128 and nothing below. The
     * whole list is checked first; a span empty or outside the surface throws
     * and nothing is written.
     */
    public function paintSpans(string $spans, int $rgba8): static;

    /**
     * Paint coverage masks in one 0xRRGGBBAA colour, in list order, each cut to
     * $clip. A mask is [string $coverage, int $width, int $left, int $top]: rows
     * of $width coverage bytes 0..255, top-down, its top-left pixel at ($left,
     * $top), which may lie off the surface (within ±2^30). Every pixel of
     * coverage above 0 is painted as a span of that coverage is by
     * paintSpans(). The whole list is checked first; a bad mask, or a clip
     * empty or outside the surface, throws and nothing is written.
     *
     * @param  list<array{string, int, int, int}>  $masks
     */
    public function paintMasks(array $masks, int $rgba8, Region $clip): static;

    /**
     * Paint another framebuffer's pixels through $placement, which maps the
     * source's pixel coordinates onto this surface: scaled, turned, moved. A
     * pixel here is painted when its centre maps inside the source, from the
     * source pixel under that point (NEAREST) or the four around it (LINEAR,
     * edges held), blended like paintSpans() with source alpha × $opacity
     * (0..255) as the alpha. Pixels outside the surface or $clip are skipped.
     * A paged source gives its current page, at its place.
     *
     * Three optional parts change what is painted, applied in this order to each
     * sampled pixel (all in whole 0..255 steps, each rounded to nearest, halves up):
     *  1. $tint, 0xRRGGBBAA, is multiplied in per channel: c = (c × t + 127) / 255,
     *     alpha included, so a tint's alpha is an opacity. 0xFFFFFFFF changes nothing.
     *  2. $opacity scales the alpha, as before.
     *  3. $coverage, Spans in this surface's coordinates, scales the alpha by the
     *     coverage of the span that holds the pixel; a pixel no span holds is skipped.
     * $window, [u0, v0, u1, v1] with u0 < u1 and v0 < v1 in the placement's source
     * coordinates (a paged source's rows are offset as the placement is), limits
     * the painted pixels to those whose centre maps inside it. The window crops
     * only: smooth sampling still reads, and holds at the edges of, the whole source.
     */
    public function paintImage(InstanceThatManagesAFramebuffer $source, Affine $placement, int $opacity = 255, Filter $filter = Filter::NEAREST, ?Region $clip = null, int $tint = 0xFFFFFFFF, ?array $window = null, ?string $coverage = null): static;
}
