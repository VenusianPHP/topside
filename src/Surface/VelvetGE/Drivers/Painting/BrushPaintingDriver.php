<?php

namespace Surface\VelvetGE\Drivers\Painting;

use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Painting\Brush;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\RasterizeDriver;
use Surface\Contracts\Rendering\Recording\RecordDriver;
use Surface\Rendering\Painters\PaintingDriver;
use Surface\Rendering\Rasterization;
use Surface\Rendering\Recorders;
use Surface\VelvetGE\Painters\VelvetPainter;

/**
 * A painting driver made of VelvetGE's painter and, for a library or an extension, a brush. A
 * package that brings a painting library writes a Brush and a driver of two methods:
 *
 *     class PlutoPaintingDriver extends BrushPaintingDriver
 *     {
 *         public function __construct()
 *         {
 *             extension_loaded('plutovg') || throw new RenderingException('ext-plutovg is not loaded.');
 *             parent::__construct();
 *         }
 *
 *         public function driver(): string { return 'plutovg'; }
 *
 *         public function brush(): Brush { return new PlutoBrush(); }
 *     }
 *
 * and registers it: Painters::manager()->extend('plutovg', fn () => new PlutoPaintingDriver()).
 * A constructor that throws RenderingException tells 'auto' the driver cannot run here.
 *
 * The brush paints the commands it takes into memory it can draw into. VelvetGE's painter does
 * the rest, so a brush that takes one kind of command is a whole painter.
 */
abstract class BrushPaintingDriver extends PaintingDriver
{
    /**
     * @param  RasterizeDriver|null  $rasterize  What VelvetGE's painter rasterizes with; null takes Rendering's default.
     * @param  bool  $directFill  As VelvetPainter takes it.
     * @param  RecordDriver|null  $record  What each painter's frames are recorded with; null takes Rendering's default.
     */
    public function __construct(
        protected ?RasterizeDriver $rasterize = null,
        protected bool $directFill = true,
        protected ?RecordDriver $record = null,
    ) {}

    /** Null: VelvetGE's painter carries out every command. */
    abstract public function brush(): ?Brush;

    public function painter(Framebuffer $target, ?Edges $edges = null): Painter
    {
        return new VelvetPainter($target, $this->rasterize ??= Rasterization::driver(), $edges, $this->directFill, $this->brush(), ($this->record ??= Recorders::driver())->recorder());
    }
}
