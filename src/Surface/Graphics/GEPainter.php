<?php

namespace Surface\Graphics;

use Surface\Contracts\Rendering\Painting\PaintTarget;
use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Graphics\Effects\Glass;
use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Framebuffers\Filter;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\Path;

/**
 * The 2D drawing API over one of its engine's framebuffers: frames, transforms, clips, shapes,
 * images, layers and effects. Each engine has its own.
 *
 * Draw calls are recorded between begin() and end() (or inside frame()) and carried out when the
 * frame ends; only what changed since the last frame is redrawn. What each call does is written
 * on Surface\Contracts\Rendering\Painting\Painter, which every engine's painter is built on: an
 * engine gives this class one, and says how one of its framebuffers is read as an image.
 */
abstract class GEPainter extends GEResource
{
    /**
     * @param GEFramebuffer $onto what it draws on: one of $engine's
     * @param Painter $inner the engine's own painter over $onto
     */
    public function __construct(
        GraphicsEngine $engine,
        protected readonly GEFramebuffer $onto,
        protected readonly Painter $inner,
    ) {
        parent::__construct($engine);
        $this->mine($onto);
    }

    /** One of this engine's framebuffers, as the engine's painter takes an image. */
    abstract protected function source(GEFramebuffer $framebuffer): PaintTarget;

    public function framebuffer(): GEFramebuffer
    {
        return $this->onto;
    }

    public function width(): int
    {
        return $this->onto->width();
    }

    public function height(): int
    {
        return $this->onto->height();
    }

    /** A whole frame: begin(), $draw with this painter, end(). */
    public function frame(callable $draw): static
    {
        $this->inner->frame(fn () => $draw($this));

        return $this;
    }

    /** @param GEFramebuffer $source one of this engine's */
    public function image(GEFramebuffer $source, float $x, float $y, ?float $width = null, ?float $height = null, float $opacity = 1.0, Filter $filter = Filter::NEAREST, ?array $radii = null, ?Color $tint = null, ?array $sourceRect = null): static
    {
        $this->mine($source);
        $this->inner->image($this->source($source), $x, $y, $width, $height, $opacity, $filter, $radii, $tint, $sourceRect);

        return $this;
    }

    /** @param callable(static): void $draw draws the layer's contents with this painter */
    public function layer(float $x, float $y, float $width, float $height, callable $draw, float $opacity = 1.0, ?array $radii = null, ?FilterChain $filter = null, ?FilterChain $backdrop = null): static
    {
        $this->inner->layer($x, $y, $width, $height, fn () => $draw($this), $opacity, $radii, $filter, $backdrop);

        return $this;
    }

    public function begin(): static
    {
        $this->inner->begin();

        return $this;
    }

    public function end(): static
    {
        $this->inner->end();

        return $this;
    }

    public function damage(): array
    {
        return $this->inner->damage();
    }

    public function invalidate(): static
    {
        $this->inner->invalidate();

        return $this;
    }

    public function canScroll(): bool
    {
        return $this->inner->canScroll();
    }

    public function scroll(Region $region, int $dx, int $dy): static
    {
        $this->inner->scroll($region, $dx, $dy);

        return $this;
    }

    public function replay(): static
    {
        $this->inner->replay();

        return $this;
    }

    public function drawing(): bool
    {
        return $this->inner->drawing();
    }

    public function push(): static
    {
        $this->inner->push();

        return $this;
    }

    public function pop(): static
    {
        $this->inner->pop();

        return $this;
    }

    public function translate(float $x, float $y): static
    {
        $this->inner->translate($x, $y);

        return $this;
    }

    public function scale(float $x, ?float $y = null): static
    {
        $this->inner->scale($x, $y);

        return $this;
    }

    public function rotate(float $radians): static
    {
        $this->inner->rotate($radians);

        return $this;
    }

    public function transform(Affine $matrix): static
    {
        $this->inner->transform($matrix);

        return $this;
    }

    public function matrix(): Affine
    {
        return $this->inner->matrix();
    }

    public function clip(?Region $region): static
    {
        $this->inner->clip($region);

        return $this;
    }

    public function clipRegion(): Region
    {
        return $this->inner->clipRegion();
    }

    public function clear(Color $color): static
    {
        $this->inner->clear($color);

        return $this;
    }

    public function fillRect(float $x, float $y, float $width, float $height, Color $color): static
    {
        $this->inner->fillRect($x, $y, $width, $height, $color);

        return $this;
    }

    public function strokeRect(float $x, float $y, float $width, float $height, Color $color, float $stroke = 1.0): static
    {
        $this->inner->strokeRect($x, $y, $width, $height, $color, $stroke);

        return $this;
    }

    public function line(float $x0, float $y0, float $x1, float $y1, Color $color, float $stroke = 1.0): static
    {
        $this->inner->line($x0, $y0, $x1, $y1, $color, $stroke);

        return $this;
    }

    public function polyline(array $points, Color $color, float $stroke = 1.0, bool $closed = false): static
    {
        $this->inner->polyline($points, $color, $stroke, $closed);

        return $this;
    }

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, Color $color): static
    {
        $this->inner->fillTriangle($x0, $y0, $x1, $y1, $x2, $y2, $color);

        return $this;
    }

    public function fillPolygon(array $points, Color $color, FillRule $rule = FillRule::NON_ZERO): static
    {
        $this->inner->fillPolygon($points, $color, $rule);

        return $this;
    }

    public function fillPath(array $contours, Color $color, FillRule $rule = FillRule::NON_ZERO): static
    {
        $this->inner->fillPath($contours, $color, $rule);

        return $this;
    }

    public function fill(Path $path, Color|Gradient $paint, FillRule $rule = FillRule::NON_ZERO): static
    {
        $this->inner->fill($path, $paint, $rule);

        return $this;
    }

    public function stroke(Path $path, Color|Gradient $paint, StrokeStyle $style): static
    {
        $this->inner->stroke($path, $paint, $style);

        return $this;
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color $color): static
    {
        $this->inner->fillEllipse($cx, $cy, $rx, $ry, $color);

        return $this;
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, Color $color, float $stroke = 1.0): static
    {
        $this->inner->strokeEllipse($cx, $cy, $rx, $ry, $color, $stroke);

        return $this;
    }

    public function roundRect(float $x, float $y, float $width, float $height, array $radii, Color $fill, ?Color $border = null, float $borderWidth = 0.0): static
    {
        $this->inner->roundRect($x, $y, $width, $height, $radii, $fill, $border, $borderWidth);

        return $this;
    }

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, Color $color, float $dx = 0.0, float $dy = 0.0): static
    {
        $this->inner->shadow($x, $y, $width, $height, $radius, $blur, $color, $dx, $dy);

        return $this;
    }

    public function glass(float $x, float $y, float $width, float $height, ?array $radii, Glass $glass, float $opacity = 1.0): static
    {
        $this->inner->glass($x, $y, $width, $height, $radii, $glass, $opacity);

        return $this;
    }
}
