<?php

namespace Surface\Graphics;

use Surface\NutsAndBolts\Path;
use Surface\Contracts\Typography\TypesetText;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Gradient;
use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Graphics\Effects\Glass;
use Surface\Contracts\Graphics\GraphicsEngine;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Graphics\DrawingContext;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Rendering\Framebuffers\Filter;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Painting\PaintTarget;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Graphics\DrawingSurface as SurfaceContract;

/**
 * A drawing context over one of Rendering's painters: every draw call goes to the painter as it
 * is. A PainterSurface hands it to render().
 *
 * It has the draw calls only. The frame is the surface's: render() opens and closes it.
 */
final class PainterContext implements DrawingContext
{
    /** @param GraphicsEngine $engine whose surfaces image() reads */
    public function __construct(
        protected readonly Painter $painter,
        protected readonly GraphicsEngine $engine,
    ) {}

    /**
     * What the painter reads when $surface is drawn as an image: its target.
     *
     * @throws GraphicsException When the surface is another engine's: an engine reads only its own.
     */
    protected function pixelsOf(SurfaceContract $surface): PaintTarget
    {
        if (! $surface instanceof PainterSurface || $surface->engine() !== $this->engine) {
            throw GraphicsException::foreignResource($this->engine->name(), $surface->engine()->name());
        }

        return $surface->paintTarget();
    }

    public function width(): int
    {
        return $this->painter->width();
    }

    public function height(): int
    {
        return $this->painter->height();
    }

    public function push(): static
    {
        $this->painter->push();

        return $this;
    }

    public function pop(): static
    {
        $this->painter->pop();

        return $this;
    }

    public function translate(float $x, float $y): static
    {
        $this->painter->translate($x, $y);

        return $this;
    }

    public function scale(float $x, ?float $y = null): static
    {
        $this->painter->scale($x, $y);

        return $this;
    }

    public function rotate(float $radians): static
    {
        $this->painter->rotate($radians);

        return $this;
    }

    public function transform(Affine $matrix): static
    {
        $this->painter->transform($matrix);

        return $this;
    }

    public function matrix(): Affine
    {
        return $this->painter->matrix();
    }

    public function clip(?Region $region): static
    {
        $this->painter->clip($region);

        return $this;
    }

    public function clipRegion(): Region
    {
        return $this->painter->clipRegion();
    }

    public function clear(Color $color): static
    {
        $this->painter->clear($color);

        return $this;
    }

    public function fillRect(float $x, float $y, float $width, float $height, Color $color): static
    {
        $this->painter->fillRect($x, $y, $width, $height, $color);

        return $this;
    }

    public function strokeRect(float $x, float $y, float $width, float $height, Color $color, float $stroke = 1.0): static
    {
        $this->painter->strokeRect($x, $y, $width, $height, $color, $stroke);

        return $this;
    }

    public function line(float $x0, float $y0, float $x1, float $y1, Color $color, float $stroke = 1.0): static
    {
        $this->painter->line($x0, $y0, $x1, $y1, $color, $stroke);

        return $this;
    }

    public function polyline(array $points, Color $color, float $stroke = 1.0, bool $closed = false): static
    {
        $this->painter->polyline($points, $color, $stroke, $closed);

        return $this;
    }

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, Color $color): static
    {
        $this->painter->fillTriangle($x0, $y0, $x1, $y1, $x2, $y2, $color);

        return $this;
    }

    public function fillPolygon(array $points, Color $color, FillRule $rule = FillRule::NON_ZERO): static
    {
        $this->painter->fillPolygon($points, $color, $rule);

        return $this;
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color $color): static
    {
        $this->painter->fillEllipse($cx, $cy, $rx, $ry, $color);

        return $this;
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, Color $color, float $stroke = 1.0): static
    {
        $this->painter->strokeEllipse($cx, $cy, $rx, $ry, $color, $stroke);

        return $this;
    }

    public function roundRect(float $x, float $y, float $width, float $height, array $radii, Color $fill, ?Color $border = null, float $borderWidth = 0.0): static
    {
        $this->painter->roundRect($x, $y, $width, $height, $radii, $fill, $border, $borderWidth);

        return $this;
    }

    public function fill(Path $path, Color|Gradient $paint, FillRule $rule = FillRule::NON_ZERO): static
    {
        $this->painter->fill($path, $paint, $rule);

        return $this;
    }

    public function stroke(Path $path, Color|Gradient $paint, StrokeStyle $style): static
    {
        $this->painter->stroke($path, $paint, $style);

        return $this;
    }

    public function image(SurfaceContract $source, float $x, float $y, ?float $width = null, ?float $height = null, float $opacity = 1.0, Filter $filter = Filter::NEAREST, ?array $radii = null, ?Color $tint = null, ?array $sourceRect = null): static
    {
        $this->painter->image($this->pixelsOf($source), $x, $y, $width, $height, $opacity, $filter, $radii, $tint, $sourceRect);

        return $this;
    }

    public function layer(float $x, float $y, float $width, float $height, callable $draw, float $opacity = 1.0, ?array $radii = null, ?FilterChain $filter = null, ?FilterChain $backdrop = null): static
    {
        // what the layer holds is drawn through this context, so the developer never meets the painter
        $this->painter->layer($x, $y, $width, $height, fn () => $draw($this), $opacity, $radii, $filter, $backdrop);

        return $this;
    }

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, Color $color, float $dx = 0.0, float $dy = 0.0): static
    {
        $this->painter->shadow($x, $y, $width, $height, $radius, $blur, $color, $dx, $dy);

        return $this;
    }

    public function glass(float $x, float $y, float $width, float $height, ?array $radii, Glass $glass, float $opacity = 1.0): static
    {
        $this->painter->glass($x, $y, $width, $height, $radii, $glass, $opacity);

        return $this;
    }

    public function text(TypesetText $text, float $x, float $y, Color $color): static
    {
        $this->painter->text($text, $x, $y, $color);

        return $this;
    }
}
