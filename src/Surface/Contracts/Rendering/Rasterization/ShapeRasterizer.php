<?php

namespace Surface\Contracts\Rendering\Rasterization;

use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;

/**
 * Paints shapes into a framebuffer. Every graphics engine's rasterizer does this much: one on a
 * CPU also answers coverage spans (Rasterizer); one on a GPU rasterizes inside its pipeline and
 * has no spans to answer.
 */
interface ShapeRasterizer
{
    /** The driver or engine that rasterizes: 'native', 'ext', 'metal'. */
    public function driver(): string;

    public function edges(): Edges;

    /** What every shape is cut to. */
    public function clip(): Region;

    /**
     * Many shapes painted in order onto $target. Each shape is a drawing command (Surface\Rendering\Painters\Painter)
     * of kind path, polyline, ellipse, ring, roundrect, roundring or shadow, whose entries after its
     * colour (the clip) are not read: this rasterizer's own clip cuts. A bad shape throws before any
     * shape of the batch is painted. With $direct, the batch reaches $target the fastest way the two
     * allow; without, a CPU rasterizer sends every shape through $target->paintSpans(), the path the
     * others are held to. A GPU rasterizer paints only into its own engine's framebuffers.
     *
     * @param  list<array<int, mixed>>  $shapes
     */
    public function fillBatch(array $shapes, Framebuffer $target, bool $direct = true): void;
}
