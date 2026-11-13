<?php

namespace Surface\VelvetGE;

use Surface\Contracts\Fonts\Font;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\Region;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Graphics\Effects\Glass;
use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Graphics\GraphicsEngine;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\Path;

class VelvetGEDevice extends GraphicsEngine
{

    public function name(): string
    {
        // TODO: Implement name() method.
    }

    public function framebuffer(): Framebuffer
    {
        // TODO: Implement framebuffer() method.
    }

    public function width(): int
    {
        // TODO: Implement width() method.
    }

    public function height(): int
    {
        // TODO: Implement height() method.
    }

    public function begin(): static
    {
        // TODO: Implement begin() method.
    }

    public function end(): static
    {
        // TODO: Implement end() method.
    }

    public function damage(): array
    {
        // TODO: Implement damage() method.
    }

    public function invalidate(): static
    {
        // TODO: Implement invalidate() method.
    }

    public function canScroll(): bool
    {
        // TODO: Implement canScroll() method.
    }

    public function scroll(Region $region, int $dx, int $dy): static
    {
        // TODO: Implement scroll() method.
    }

    public function frame(callable $draw): static
    {
        // TODO: Implement frame() method.
    }

    public function replay(): static
    {
        // TODO: Implement replay() method.
    }

    public function drawing(): bool
    {
        // TODO: Implement drawing() method.
    }

    public function push(): static
    {
        // TODO: Implement push() method.
    }

    public function pop(): static
    {
        // TODO: Implement pop() method.
    }

    public function translate(float $x, float $y): static
    {
        // TODO: Implement translate() method.
    }

    public function scale(float $x, ?float $y = null): static
    {
        // TODO: Implement scale() method.
    }

    public function rotate(float $radians): static
    {
        // TODO: Implement rotate() method.
    }

    public function transform(Affine $matrix): static
    {
        // TODO: Implement transform() method.
    }

    public function matrix(): Affine
    {
        // TODO: Implement matrix() method.
    }

    public function clip(?Region $region): static
    {
        // TODO: Implement clip() method.
    }

    public function clipRegion(): Region
    {
        // TODO: Implement clipRegion() method.
    }

    public function clear(Color $color): static
    {
        // TODO: Implement clear() method.
    }

    public function fillRect(float $x, float $y, float $width, float $height, Color $color): static
    {
        // TODO: Implement fillRect() method.
    }

    public function strokeRect(float $x, float $y, float $width, float $height, Color $color, float $stroke = 1.0): static
    {
        // TODO: Implement strokeRect() method.
    }

    public function line(float $x0, float $y0, float $x1, float $y1, Color $color, float $stroke = 1.0): static
    {
        // TODO: Implement line() method.
    }

    public function polyline(array $points, Color $color, float $stroke = 1.0, bool $closed = false): static
    {
        // TODO: Implement polyline() method.
    }

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, Color $color): static
    {
        // TODO: Implement fillTriangle() method.
    }

    public function fillPolygon(array $points, Color $color, FillRule $rule = FillRule::NON_ZERO): static
    {
        // TODO: Implement fillPolygon() method.
    }

    public function fillPath(array $contours, Color $color, FillRule $rule = FillRule::NON_ZERO): static
    {
        // TODO: Implement fillPath() method.
    }

    public function fill(Path $path, Gradient|Color $paint, FillRule $rule = FillRule::NON_ZERO): static
    {
        // TODO: Implement fill() method.
    }

    public function stroke(Path $path, Gradient|Color $paint, \Surface\Contracts\Graphics\StrokeStyle $style): static
    {
        // TODO: Implement stroke() method.
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color $color): static
    {
        // TODO: Implement fillEllipse() method.
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, Color $color, float $stroke = 1.0): static
    {
        // TODO: Implement strokeEllipse() method.
    }

    public function roundRect(float $x, float $y, float $width, float $height, array $radii, Color $fill, ?Color $border = null, float $borderWidth = 0.0): static
    {
        // TODO: Implement roundRect() method.
    }

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, Color $color, float $dx = 0.0, float $dy = 0.0): static
    {
        // TODO: Implement shadow() method.
    }

    public function image(Framebuffer $source, float $x, float $y, ?float $width = null, ?float $height = null, float $opacity = 1.0, Filter $filter = Filter::NEAREST, ?array $radii = null, ?Color $tint = null, ?array $sourceRect = null): static
    {
        // TODO: Implement image() method.
    }

    public function layer(float $x, float $y, float $width, float $height, callable $draw, float $opacity = 1.0, ?array $radii = null, ?FilterChain $filter = null, ?FilterChain $backdrop = null): static
    {
        // TODO: Implement layer() method.
    }

    public function glass(float $x, float $y, float $width, float $height, ?array $radii, Glass $glass, float $opacity = 1.0): static
    {
        // TODO: Implement glass() method.
    }

    public function text(string $text, float $x, float $y, Color $color, Font $font): static
    {
        // TODO: Implement text() method.
    }

    public function textBounds(string $text, Font $font): array
    {
        // TODO: Implement textBounds() method.
    }
}