<?php

namespace Surface\Contracts\Graphics;

use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\ShapeRasterizer;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Raycaster;

/**
 * One graphics library, and the way in to it: 'velvet' on the CPU, 'metal', 'opengl', 'vulkan',
 * 'sdl3' on a GPU. An engine hands out its own parts, each behind the contract the others share:
 * a framebuffer, a rasterizer, a raycaster and a painter.
 *
 *     $engine = Engines::engine('metal');
 *     $frame = $engine->framebuffer(640, 480);
 *     $engine->rasterizer($frame->whole())->fillBatch($shapes, $frame);
 *     $engine->painter(['framebuffer' => $frame])->frame(fn (Painter $g) => $g->clear($black));
 *
 * An engine's parts work with each other. A GPU engine's rasterizer and raycaster paint only into
 * that engine's framebuffers; pixels from anywhere else reach one through the Framebuffer contract
 * (writeRgba8(), paintImage()), which uploads. Velvet's parts are Rendering's drivers, so any of
 * them works with any other.
 *
 * A part an engine does not have throws DrawingException naming it: ask offers() first.
 */
interface GraphicsEngine
{
    public function name(): string;

    public function offers(Part $part): bool;

    /**
     * A framebuffer this engine owns, $width by $height pixels.
     *
     * @param  array<string, mixed>  $args  What the engine takes besides the size; each engine names its own.
     *
     * @throws DrawingException When the engine cannot make it, or does not take an argument.
     */
    public function framebuffer(int $width, int $height, array $args = []): Framebuffer;

    /**
     * Paints shapes, cut to $clip, into this engine's framebuffers.
     *
     * @param  Edges|null  $edges  Null: the engine's default.
     */
    public function rasterizer(Region $clip, ?Edges $edges = null): ShapeRasterizer;

    /** Casts rays through $grid, and paints its views into this engine's framebuffers. */
    public function raycaster(Grid $grid): Raycaster;

    /**
     * The drawing API over one of this engine's framebuffers: 'framebuffer' names one it made,
     * 'width' and 'height' have it make one, 'output' draws for a canvas, a window or a display.
     *
     * @param  array<string, mixed>  $args  Each engine names what else it takes.
     *
     * @throws DrawingException When the painter cannot be built from $args.
     */
    public function painter(array $args = []): Painter;
}
