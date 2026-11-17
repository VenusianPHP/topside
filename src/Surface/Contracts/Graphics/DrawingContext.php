<?php

namespace Surface\Contracts\Graphics;

use Surface\Contracts\Graphics\Effects\Glass;
use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Rendering\Framebuffers\Filter;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\Path;
use Surface\Contracts\Typography\TypesetText;

interface DrawingContext
{
    // Size of what you're drawing on
    public function width(): int;

    public function height(): int;

    // Transform stack
    public function pop(): static;

    public function push(): static;

    public function translate(float $x, float $y): static;

    public function scale(float $x, ?float $y = null): static;

    public function rotate(float $radians): static;

    public function transform(Affine $matrix): static;

    public function matrix(): Affine;

    // Clipping
    public function clip(?Region $region): static;
    public function clipRegion(): Region;

    // Shapes
    public function clear(Color $color): static;

    public function fillRect(float $x, float $y, float $width, float $height, Color $color): static;

    public function strokeRect(float $x, float $y, float $width, float $height, Color $color, float $stroke = 1.0): static;

    public function line(float $x0, float $y0, float $x1, float $y1, Color $color, float $stroke = 1.0): static;

    public function polyline(array $points, Color $color, float $stroke = 1.0, bool $closed = false): static;

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, Color $color): static;

    public function fillPolygon(array $points, Color $color, FillRule $rule = FillRule::NON_ZERO): static;

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color $color): static;

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, Color $color, float $stroke = 1.0): static;

    public function roundRect(float $x, float $y, float $width, float $height, array $radii, Color $fill, ?Color $border = null, float $borderWidth = 0.0): static;

    // Paths
    public function fill(Path $path, Color|Gradient $paint, FillRule $rule = FillRule::NON_ZERO): static;

    public function stroke(Path $path, Color|Gradient $paint, StrokeStyle $style): static;

    // Images, layers, effects
    /**
     * Another of this engine's surfaces, drawn with its top-left at (x, y), at its own size unless a
     * width and height are given. $tint multiplies every pixel, alpha included. $radii [tl, tr, br,
     * bl] cut it to a rounded rect of the box drawn into. $sourceRect [x, y, w, h], in the source's
     * pixels, draws only that part; a negative w or h mirrors that axis.
     *
     * @param  float  $opacity  0..1
     * @param  array{float, float, float, float}|null  $radii
     * @param  array{float, float, float, float}|null  $sourceRect
     */
    public function image(DrawingSurface $source, float $x, float $y, ?float $width = null, ?float $height = null, float $opacity = 1.0, Filter $filter = Filter::NEAREST, ?array $radii = null, ?Color $tint = null, ?array $sourceRect = null): static;

    public function layer(
        float $x,
        float $y,
        float $width,
        float $height,
        callable $draw,
        float $opacity = 1.0,
        ?array $radii = null,
        ?FilterChain $filter = null,
        ?FilterChain $backdrop = null
    ): static;

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, Color $color, float $dx = 0.0, float $dy = 0.0): static;

    public function glass(float $x, float $y, float $width, float $height, ?array $radii, Glass $glass, float $opacity = 1.0): static;

    // Text
    /**
     * Text Typography has set (Typography::set()), drawn with its top-left at (x, y) in one colour:
     * what it measured is where it draws.
     */
    public function text(TypesetText $text, float $x, float $y, Color $color): static;
}