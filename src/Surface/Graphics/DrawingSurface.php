<?php

namespace Surface\Graphics;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\GraphicsEngine;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Graphics\HostFormatStage;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Graphics\DrawingSurface as SurfaceContract;
use Surface\Contracts\Graphics\PacedStage;
use Surface\Contracts\HumanInput\InputStage;

abstract class DrawingSurface implements SurfaceContract
{
    /** Whether a paced stage has been asked to show this surface and has not yet. */
    protected bool $due = false;

    /** How many frames have reached the stage. */
    protected int $shown = 0;

    /**
     * @param GraphicsEngine $engine
     * @param ActorStage|null $stage null: an offscreen surface
     */
    public function __construct(
        protected readonly GraphicsEngine $engine,
        protected readonly ?ActorStage $stage = null,
    ) {}

    public function engine(): GraphicsEngine
    {
        return $this->engine;
    }

    /**
     * Hands the finished frame to the stage. It draws nothing: render() made the frame.
     *
     * A stage that takes input has it settled first, each time. A stage that knows its display's
     * refresh (a PacedStage) is shown the surface once when the display is next due a frame, as it
     * is then: frames presented in between cost nothing to show. Any other stage is shown at once.
     *
     * @throws GraphicsException When the surface is offscreen.
     */
    public function present(): void
    {
        if (is_null($this->stage)) {
            throw GraphicsException::noStage();
        }
        if ($this->stage instanceof InputStage) {
            $this->stage->settleInput();
        }

        if (! $this->stage instanceof PacedStage) {
            $this->show();

            return;
        }
        if (! $this->due) {
            $this->due = true;
            $this->stage->whenDue(function (): void {
                $this->due = false;
                $this->show();
            });
        }
    }

    /** How many frames have reached the stage: on a paced stage, no more than its display refreshed. */
    public function shown(): int
    {
        return $this->shown;
    }

    /**
     * The surface as it is now, onto the stage. A stage that shows the surface's own format as it
     * is (a HostFormatStage) is handed the pixels in it, so nothing reorders them; any other
     * stage is handed RGBA8.
     */
    protected function show(): void
    {
        $pixels = $this->stage instanceof HostFormatStage ? $this->ownPixels($this->stage->hostFormats()) : null;
        is_null($pixels)
            ? $this->stage->display($this->read(), $this->width(), $this->height())
            : $this->stage->displayPixels($pixels, $this->width(), $this->height(), $this->format());
        $this->shown++;
    }

    /**
     * The surface's pixels in its own format(), rows from the top, when that is one of $formats
     * and the engine can give them as they are; null when not, and the stage is given read().
     * An engine that holds its pixels where PHP can have them as they are says so here.
     *
     * @param list<FormatSpec> $formats
     */
    protected function ownPixels(array $formats): ?string
    {
        return null;
    }

    /**
     * Will let go of what the surface holds in its library. A surface that holds nothing leaves this as it is.
     */
    public function release(): void {}
}