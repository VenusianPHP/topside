<?php

namespace Surface\Contracts\Graphics;

use Surface\Contracts\Rendering\Enums\FillRule;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\Path;

/**
 * What a frame is drawn with. Coordinates are pixels from the surface's top left, through the current transform;
 * whole numbers are pixel edges. Each frame starts with no transform and nothing pushed.
 *
 * A paint is a Color or a Gradient (a LinearGradient or a RadialGradient). A gradient is given in the units of the
 * shape it paints and goes through the transform with it. How an engine blends colours and spreads a gradient's
 * stops is the engine's own.
 */
interface Canvas
{
    /** @return array{int, int} width and height of the frame being drawn */
    public function size(): array;

    /** Every pixel, this colour, whatever the transform. */
    public function clear(Color $color): void;

    /** Keeps the current transform, for pop() to bring back. */
    public function push(): void;

    /**
     * Brings back the transform the last push() kept.
     *
     * @throws GraphicsException when nothing is pushed
     */
    public function pop(): void;

    /** What is drawn next moves by (x, y), in the current transform's units. */
    public function translate(float $x, float $y): void;

    /** What is drawn next is scaled by (sx, sy) about the current origin. */
    public function scale(float $sx, float $sy): void;

    /** What is drawn next turns by $radians about the current origin; positive turns clockwise on screen. */
    public function rotate(float $radians): void;

    public function fillRect(float $x, float $y, float $width, float $height, Color|Gradient $paint): void;

    /** A rect whose four corners are rounded by $radius. */
    public function fillRoundRect(float $x, float $y, float $width, float $height, float $radius, Color|Gradient $paint): void;

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color|Gradient $paint): void;

    /** The ellipse's outline, $width wide, centred on it. */
    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, float $width, Color|Gradient $paint): void;

    /** The path's subpaths, each closed, filled under $rule. Its curves are the engine's to draw. */
    public function fillPath(Path $path, FillRule $rule, Color|Gradient $paint): void;

    /** The path's outline, $width wide, centred on it; a subpath is joined end to start only where the path closes it. */
    public function strokePath(Path $path, float $width, Color|Gradient $paint): void;
}
