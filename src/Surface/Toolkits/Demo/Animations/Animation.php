<?php

namespace Surface\Toolkits\Demo\Animations;

use Random\Engine\Mt19937;
use Random\Randomizer;
use Surface\Contracts\Graphics\Canvas;
use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Path;

/**
 * One of the animations on the Animations page. Each is a port of a Blend2D demo
 * (blend2d-testing/demos/bl_demo_*.cpp): the same shapes, counts, speeds and colours, drawn by
 * whichever graphics engine the application draws its canvases with.
 *
 * Blend2D's demos move things a fixed step per tick at 120 ticks a second. Here step() is given
 * how many of those ticks the time since the last frame is worth, as a fraction, so an animation
 * moves at one speed whatever the frame rate. Sizes are in points: the page scales the painter
 * to the canvas's pixels.
 */
abstract class Animation
{
    protected Randomizer $random;

    /** The canvas's size in points, as of the last step. */
    protected float $width = 580.0;

    protected float $height = 420.0;

    public function __construct(int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    /** The name on the page's menu. */
    abstract public function title(): string;

    /** One sentence on what the animation draws. */
    abstract public function about(): string;

    /**
     * The animation's own controls: sliders, menus, checkboxes. $changed is called after any of
     * them changes something, so the page can show the new count.
     */
    abstract public function controls(ViewBuilder $ui, \Closure $changed): void;

    /** Move everything on by $ticks (1.0 is one of Blend2D's 120-a-second steps). */
    abstract public function step(float $ticks): void;

    abstract public function draw(Canvas $g): void;

    /** How many things one frame draws: the page shows it beside the frame rate. */
    abstract public function count(): int;

    /** The canvas's size in points; an animation places new things inside it. */
    public function resize(float $width, float $height): void
    {
        [$this->width, $this->height] = [$width, $height];
    }

    protected function next(): float
    {
        return $this->random->nextFloat();
    }

    protected function sign(): float
    {
        return $this->next() < 0.5 ? 1.0 : -1.0;
    }

    /** A colour from a 32-bit 0xAARRGGBB, as Blend2D's BLRgba32 holds one. */
    protected static function argb(int $value): Color
    {
        return Color::rgba(($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF, (($value >> 24) & 0xFF) / 255);
    }

    protected static function rect(float $x, float $y, float $width, float $height): Path
    {
        return (new Path)->moveTo($x, $y)->lineTo($x + $width, $y)->lineTo($x + $width, $y + $height)->lineTo($x, $y + $height)->close();
    }

    /** Adds a circle to $path as its own contour, as Blend2D's add_circle does. */
    protected static function circle(Path $path, float $cx, float $cy, float $r): void
    {
        $k = $r * 0.5522847498;
        $path->moveTo($cx + $r, $cy)
            ->cubicTo($cx + $r, $cy + $k, $cx + $k, $cy + $r, $cx, $cy + $r)
            ->cubicTo($cx - $k, $cy + $r, $cx - $r, $cy + $k, $cx - $r, $cy)
            ->cubicTo($cx - $r, $cy - $k, $cx - $k, $cy - $r, $cx, $cy - $r)
            ->cubicTo($cx + $k, $cy - $r, $cx + $r, $cy - $k, $cx + $r, $cy)
            ->close();
    }
}
