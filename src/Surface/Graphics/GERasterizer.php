<?php

namespace Surface\Graphics;

use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Path;

/**
 * Draws one shape into one of its engine's framebuffers, at once. Each engine has its own.
 *
 * It keeps nothing between calls: no frame, no transform, no record of what was drawn. A path is
 * in the framebuffer's own pixels. The painter is the tool for anything more.
 */
abstract class GERasterizer extends GEResource
{
    /** Whether edges are antialiased or hard: set when the engine made this rasterizer. */
    abstract public function edges(): Edges;

    abstract protected function fillPath(GEFramebuffer $onto, Path $path, Color $color, FillRule $rule, Region $clip): void;

    abstract protected function strokePath(GEFramebuffer $onto, Path $path, Color $color, StrokeStyle $style, Region $clip): void;

    /**
     * @param Region|null $clip pixels outside it are left alone; null: the whole surface
     *
     * @throws GraphicsException When $onto is another engine's.
     */
    public function fill(GEFramebuffer $onto, Path $path, Color $color, FillRule $rule = FillRule::NON_ZERO, ?Region $clip = null): static
    {
        $this->mine($onto);
        $clip = $this->clipOn($onto, $clip);
        if (! is_null($clip)) {
            $this->fillPath($onto, $path, $color, $rule, $clip);
        }

        return $this;
    }

    /**
     * @param Region|null $clip pixels outside it are left alone; null: the whole surface
     *
     * @throws GraphicsException When $onto is another engine's.
     */
    public function stroke(GEFramebuffer $onto, Path $path, Color $color, StrokeStyle $style, ?Region $clip = null): static
    {
        $this->mine($onto);
        $clip = $this->clipOn($onto, $clip);
        if (! is_null($clip)) {
            $this->strokePath($onto, $path, $color, $style, $clip);
        }

        return $this;
    }

    /** $clip cut to $onto's surface; null when nothing of it is on the surface. */
    protected function clipOn(GEFramebuffer $onto, ?Region $clip): ?Region
    {
        $whole = Region::wholeSurface($onto->width(), $onto->height());

        return is_null($clip) ? $whole : $whole->intersect($clip);
    }
}
