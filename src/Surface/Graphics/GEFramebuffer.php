<?php

namespace Surface\Graphics;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Graphics\PacedStage;
use Surface\Contracts\HumanInput\InputStage;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\NutsAndBolts\Color;

/**
 * Holds an engine's pixels. Each engine has its own.
 *
 * Pixels are read and written as RGBA8: four bytes a pixel, red first, rows from the top, colour
 * not premultiplied. That is so whatever the engine holds them as. On a GPU engine a read copies
 * back from the GPU and a write uploads to it.
 *
 * One made by GraphicsEngine::attach() has a stage, and present() shows it there. On a stage that
 * knows when its display is due a frame (a PacedStage), present() does not show at once: the
 * latest frame is shown at the next refresh, so drawing faster than the display costs no showing.
 */
abstract class GEFramebuffer extends GEResource
{
    /** Where present() shows this framebuffer; null for one with no stage. */
    protected ?ActorStage $stage = null;

    /** Whether the stage has been asked to show this framebuffer at its next refresh, and has not yet. */
    protected bool $due = false;

    /** How many times it has been shown on its stage. */
    protected int $shown = 0;

    abstract public function width(): int;

    abstract public function height(): int;

    /** How the engine holds the pixels. */
    abstract public function format(): FormatSpec;

    /** The pixels of $region as RGBA8. */
    abstract protected function readRgba8(Region $region): string;

    /** RGBA8 bytes into $region: strlen($rgba8) is $region's width x height x 4. */
    abstract protected function writeRgba8(string $rgba8, Region $region): void;

    /** Every pixel to $color. */
    abstract public function clear(Color $color): static;

    /**
     * @param Region|null $region null: the whole surface
     *
     * @throws GraphicsException When $region is not inside the surface.
     */
    public function read(?Region $region = null): string
    {
        return $this->readRgba8($this->inside($region));
    }

    /**
     * @param Region|null $region null: the whole surface
     *
     * @throws GraphicsException When $region is not inside the surface, or $rgba8 is not its size.
     */
    public function write(string $rgba8, ?Region $region = null): static
    {
        $region = $this->inside($region);
        if (strlen($rgba8) !== $region->width * $region->height * 4) {
            throw GraphicsException::wrongSize(strlen($rgba8), $region);
        }
        $this->writeRgba8($rgba8, $region);

        return $this;
    }

    /** The stage this framebuffer was made for, when the engine attached it to one. */
    public function stage(): ?ActorStage
    {
        return $this->stage;
    }

    /**
     * Shows what the framebuffer holds on its stage: at once, or on a PacedStage at the display's
     * next refresh, where it shows what the framebuffer holds by then. Presented again before
     * that refresh, it is still shown once.
     *
     * @throws GraphicsException When it has no stage.
     */
    public function present(): static
    {
        if (is_null($this->stage)) {
            throw GraphicsException::noStage();
        }

        $this->settleInput();

        if (! $this->stage instanceof PacedStage) {
            $this->show();
            $this->shown++;

            return $this;
        }

        if (! $this->due) {
            $this->due = true;
            $this->stage->whenDue(function (): void {
                $this->due = false;
                $this->show();
                $this->shown++;
            });
        }

        return $this;
    }

    /** How many times this framebuffer has been shown on its stage: on a PacedStage, at most once a refresh. */
    public function shown(): int
    {
        return $this->shown;
    }

    /**
     * Puts what the framebuffer holds on its stage, now. As pixels through the stage; an engine
     * that shows another way (a GPU engine into its own layer) does that here.
     */
    protected function show(): void
    {
        $this->stage->display($this->read(), $this->width(), $this->height());
    }

    /**
     * The frame presented was drawn from the input its stage had: those edges are over. Done as
     * it is presented, not as it is shown, so input that comes before a paced stage's refresh is
     * kept for the next frame.
     */
    protected function settleInput(): void
    {
        if ($this->stage instanceof InputStage) {
            $this->stage->settleInput();
        }
    }

    /**
     * Sets the stage present() shows this framebuffer on. An engine calls it from attach().
     *
     * @internal
     */
    public function onto(ActorStage $stage): static
    {
        $this->stage = $stage;

        return $this;
    }

    /** @throws GraphicsException */
    protected function inside(?Region $region): Region
    {
        $whole = Region::wholeSurface($this->width(), $this->height());
        if (is_null($region)) {
            return $whole;
        }
        if ($region->isEmpty() || $region->x < 0 || $region->y < 0 || $region->right() > $whole->width || $region->bottom() > $whole->height) {
            throw GraphicsException::outsideSurface($region, $whole->width, $whole->height);
        }

        return $region;
    }
}
