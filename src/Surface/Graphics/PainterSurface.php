<?php

namespace Surface\Graphics;

use Closure;
use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\GraphicsEngine;
use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Painting\PaintTarget;

/**
 * A surface an engine draws on with one of Rendering's painters: what it draws on is a
 * PaintTarget, and a frame is the painter's. An engine's surface extends this and says what its
 * target is, its size and format, and how its pixels are read.
 */
abstract class PainterSurface extends DrawingSurface
{
    protected readonly PainterContext $context;

    public function __construct(
        GraphicsEngine $engine,
        protected readonly Painter $painter,
        ?ActorStage $stage = null,
    ) {
        parent::__construct($engine, $stage);
        $this->context = new PainterContext($painter, $engine);
    }

    /** What the painter draws on: the engine's own framebuffer. */
    abstract public function paintTarget(): PaintTarget;

    /**
     * One frame: $draw's calls are recorded, then drawn in the order made.
     *
     * @param Closure(PainterContext): void $draw
     */
    public function render(Closure $draw): static
    {
        $this->painter->frame(fn () => $draw($this->context));

        return $this;
    }

    /**
     * Moves the pixels of $region between frames, by the painter, which redraws the region in the
     * next frame.
     *
     * @throws DrawingException When the framebuffer does not keep its frame, or a frame is open.
     */
    public function scroll(Region $region, int $dx, int $dy): static
    {
        $this->painter->scroll($region, $dx, $dy);

        return $this;
    }

    /**
     * A frame something other than the painter writes (a raycaster's view): $write is given the
     * target. The painter draws its next frame whole, since this one is not the frame it knows.
     *
     * @param Closure(PaintTarget): void $write
     */
    public function frame(Closure $write): void
    {
        $write($this->paintTarget());
        $this->painter->invalidate();
    }
}
