<?php

namespace Surface\Contracts\Rendering\Framebuffers;

use Surface\Contracts\Graphics\Effects\FilterChain;

/**
 * A store that runs effects: filter chains, the levels of a blur pyramid and
 * glass panes. Optional. A store that leaves it out has its effects run by
 * VelvetGE's own arithmetic over its pixels.
 */
interface EffectsPixelStore extends PixelStore
{
    /**
     * Framebuffer::filter() on this store: $region is inside the store.
     *
     * @param  int  $row  The surface row this store's row 0 stands for (as in paintRgba8()): where noise counts y from.
     * @param  bool  $premultiplied  The region's pixels are premultiplied RGBA8 (see Framebuffer::filter()).
     */
    public function filter(FilterChain $chain, Region $region, int $row = 0, bool $premultiplied = false): void;

    /**
     * Glass (drawing-path spec G4): this RGBA8 store, ceil(w / 2) x ceil(h / 2), as one level down of a dual-filter
     * (Kawase) pyramid from the region of an RGBA8 store of the same driver.
     */
    public function kawaseDown(PixelStore $source, Region $region): void;

    /** One level up: this RGBA8 store, at most twice $source on each side, from it by the 8-tap tent. */
    public function kawaseUp(PixelStore $source): void;

    /**
     * A glass pane blended into this store inside $clip: its rounded box sampled from $blur ($scale store pixels a
     * pixel, from ($originX, $originY)), and $sharp at full size, both RGBA8 stores of the same driver. The arithmetic
     * is ext-fb's fb_paint_glass(); PHPLandGlass is its PHP twin.
     *
     * @param  array{float, float, float, float, array{float, float, float, float}}  $geometry  x0, y0, x1, y1, radii
     * @param  list<float|int>  $look  [frost, refraction, bevel, saturation, light, grain, tint, rim, alpha]
     */
    public function paintGlass(array $geometry, PixelStore $blur, int $scale, ?PixelStore $sharp, int $originX, int $originY, array $look, Region $clip): ?Region;
}
