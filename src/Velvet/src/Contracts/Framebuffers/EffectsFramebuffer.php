<?php

namespace VelvetGE\Contracts\Framebuffers;

use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Rendering\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;

/** A framebuffer that runs effects over its own pixels: filter chains, Kawase blur levels, glass. */
interface EffectsFramebuffer extends InstanceThatManagesAFramebuffer
{
    /**
     * Replaces the pixels of $region (null: the whole surface) with the result of a filter chain run over them. The
     * chain's `source` is those pixels as they are (straight RGBA8; the steps work on premultiplied values and the result
     * is straightened again); a blur, a displacement or a flood reads and writes only inside the region, and noise is a
     * function of the position on the surface, so the same chain over the same pixels gives the same bytes at any place
     * and on every driver. A paged framebuffer takes a region inside its current page.
     *
     * With $premultiplied the region's pixels are premultiplied RGBA8, as a layer drawn over nothing leaves them: they
     * are the chain's source as they are, and the result is still written straight. An empty chain then only straightens.
     */
    public function filter(FilterChain $chain, ?Region $region = null, bool $premultiplied = false): static;

    /**
     * Glass (drawing-path spec G4), on RGBA8 scratch frames of one driver: this frame, ceil(w / 2) x ceil(h / 2), as one
     * dual-filter (Kawase) level down from the region of $source. See PixelStore::kawaseDown().
     */
    public function kawaseDown(InstanceThatManagesAFramebuffer $source, Region $region): static;

    /** One level up: this RGBA8 frame, at most twice $source on each side, from it by the 8-tap tent. */
    public function kawaseUp(InstanceThatManagesAFramebuffer $source): static;

    /**
     * A glass pane blended into this framebuffer inside $clip, from a blurred level ($scale pixels a pixel, from
     * ($originX, $originY)) and the sharp capture, RGBA8 frames of the same driver. See PixelStore::paintGlass().
     *
     * @param  array{float, float, float, float, array{float, float, float, float}}  $geometry  x0, y0, x1, y1, radii
     * @param  list<float|int>  $look  [frost, refraction, bevel, saturation, light, grain, tint, rim, alpha]
     */
    public function paintGlass(array $geometry, InstanceThatManagesAFramebuffer $blur, int $scale, ?InstanceThatManagesAFramebuffer $sharp, int $originX, int $originY, array $look, Region $clip): static;
}
