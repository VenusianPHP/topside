<?php

namespace Surface\Contracts\Rendering\Rasterization;

use Surface\Contracts\Rendering\Framebuffers\Region;
use VelvetGE\Contracts\Framebuffers\CompositingFramebuffer;

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
     * A shape this rasterizer has no way to draw is not drawn, and is answered as it was given:
     * nothing else is asked to draw it, and nothing throws. The caller decides what becomes of it.
     *
     * @param  list<array<int, mixed>>  $shapes
     * @return list<array<int, mixed>>  the shapes not drawn, in order; [] when every shape was
     */
    public function fillBatch(array $shapes, CompositingFramebuffer $target, bool $direct = true): array;
}
