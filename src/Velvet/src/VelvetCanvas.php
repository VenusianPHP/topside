<?php

namespace VelvetGE;

use Surface\Contracts\Graphics\Canvas;
use Surface\Contracts\Rendering\Enums\FillRule;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Rendering\Recording\Recorder;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\LinearGradient;
use Surface\NutsAndBolts\Path;

/**
 * Graphics' Canvas, recorded: each call is the recorder's own call for it, transforms included, so the recorder
 * puts every point through the transform and works out where each command writes.
 */
final readonly class VelvetCanvas implements Canvas
{
    public function __construct(
        private Recorder $recorder,
        private int $width,
        private int $height,
    ) {}

    public function size(): array
    {
        return [$this->width, $this->height];
    }

    public function clear(Color $color): void
    {
        $this->recorder->clear(self::rgba($color));
    }

    public function fillRect(float $x, float $y, float $width, float $height, Color|Gradient $paint): void
    {
        $this->recorder->fillRect($x, $y, $width, $height, self::paint($paint));
    }

    public function fillRoundRect(float $x, float $y, float $width, float $height, float $radius, Color|Gradient $paint): void
    {
        $this->recorder->roundRect($x, $y, $width, $height, [$radius, $radius, $radius, $radius], self::paint($paint), null, 0.0);
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color|Gradient $paint): void
    {
        $this->recorder->fillEllipse($cx, $cy, $rx, $ry, self::paint($paint));
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, float $width, Color|Gradient $paint): void
    {
        $this->recorder->strokeEllipse($cx, $cy, $rx, $ry, self::paint($paint), $width);
    }

    public function fillPath(Path $path, FillRule $rule, Color|Gradient $paint): void
    {
        $this->recorder->fillPath($path->ops(), $rule, self::paint($paint));
    }

    public function strokePath(Path $path, float $width, Color|Gradient $paint): void
    {
        $this->recorder->strokePath($path->ops(), self::paint($paint), $width);
    }

    /**
     * A paint as the recorder takes one: a colour as 0xRRGGBBAA, a gradient as [kind, geometry, stops, spread] with
     * its stops' colours the same way.
     */
    private static function paint(Color|Gradient $paint): int|array
    {
        if ($paint instanceof Color) {
            return self::rgba($paint);
        }
        $stops = [];
        foreach ($paint->stops as [$offset, $color]) {
            $stops[] = [(float) $offset, self::rgba($color)];
        }

        return $paint instanceof LinearGradient
            ? ['linear', [$paint->x0, $paint->y0, $paint->x1, $paint->y1], $stops, $paint->spread->value]
            : ['radial', [$paint->cx, $paint->cy, $paint->radius], $stops, $paint->spread->value];
    }

    private static function rgba(Color $color): int
    {
        return ((int) round($color->red * 255) << 24) | ((int) round($color->green * 255) << 16)
            | ((int) round($color->blue * 255) << 8) | (int) round($color->alpha * 255);
    }

    public function push(): void
    {
        $this->recorder->push();
    }

    public function pop(): void
    {
        $this->recorder->depth() > 0 || throw GraphicsException::nothingPushed('velvet');
        $this->recorder->pop();
    }

    public function translate(float $x, float $y): void
    {
        $this->recorder->concat(1.0, 0.0, 0.0, 1.0, $x, $y);
    }

    public function scale(float $sx, float $sy): void
    {
        $this->recorder->concat($sx, 0.0, 0.0, $sy, 0.0, 0.0);
    }

    public function rotate(float $radians): void
    {
        $cos = cos($radians);
        $sin = sin($radians);
        $this->recorder->concat($cos, $sin, -$sin, $cos, 0.0, 0.0);
    }
}
