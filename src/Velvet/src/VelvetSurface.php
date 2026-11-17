<?php

namespace VelvetGE;

use Closure;
use Surface\Graphics\PainterSurface;
use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\GraphicsEngine;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Painting\PaintTarget;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use VelvetGE\Contracts\Framebuffers\CompositingFramebuffer;
use VelvetGE\Contracts\Framebuffers\RingFramebuffer;

/**
 * A Velvet surface: a framebuffer from Velvet's framebuffer module and the painter from its
 * painting module that draws into it.
 */
class VelvetSurface extends PainterSurface
{
    public function __construct(
        GraphicsEngine $engine,
        protected readonly CompositingFramebuffer&PaintTarget $framebuffer,
        Painter $painter,
        ?ActorStage $stage = null,
    ) {
        parent::__construct($engine, $painter, $stage);
    }

    public function width(): int
    {
        return $this->framebuffer->viewportWidth();
    }

    public function height(): int
    {
        return $this->framebuffer->viewportHeight();
    }

    public function format(): FormatSpec
    {
        return $this->framebuffer->hostFormat();
    }

    public function read(): string
    {
        return $this->framebuffer->toRgba8();
    }

    /** The framebuffer's pixels as it holds them, when the stage shows that format as it is. */
    protected function ownPixels(array $formats): ?string
    {
        $own = $this->framebuffer->hostFormat();
        foreach ($formats as $format) {
            if ($format->equals($own)) {
                return $this->framebuffer->flush($own);
            }
        }

        return null;
    }

    /**
     * A frame something other than the painter writes (a raycaster's view): $write is given the
     * framebuffer. A ring is repaired before and flipped after, as the painter does for its own
     * frames, and the painter draws its next frame whole, since this one is not the frame it knows.
     *
     * @param Closure(CompositingFramebuffer&PaintTarget): void $write
     */
    public function frame(Closure $write): void
    {
        $ring = $this->framebuffer instanceof RingFramebuffer ? $this->framebuffer : null;
        // a back a scroll already repaired holds the last frame: age() 1
        if (! is_null($ring) && $ring->age() !== 1) {
            $ring->repair();
        }
        $write($this->framebuffer);
        $ring?->present();
        $this->painter->invalidate();
    }

    /** The framebuffer from Velvet's framebuffer module. */
    public function paintTarget(): CompositingFramebuffer&PaintTarget
    {
        return $this->framebuffer;
    }
}
