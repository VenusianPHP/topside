<?php

namespace Surface\Contracts\Rendering\Recording;

use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Graphics\Effects\Glass;
use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\Filter;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Typography\Font;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\Path;

/**
 * What a painter records a frame with: the transform and clip state, the lowering of each draw
 * call to a command in framebuffer pixels, and the comparison of a frame with the one before it.
 * A painter has one recorder for its whole life; every painter, on the CPU or the GPU, draws the
 * commands a recorder hands it.
 *
 * The draw and state calls are Painter's, with the same arguments, the same checks and the same
 * DrawingException for each: a painter forwards them here.
 *
 * Commands, in call order, points already transformed, colours 0xRRGGBBAA, every clip a rect
 * inside the surface:
 *
 *     ['clear', rgba]                                     the whole surface
 *     ['clear', rgba, Region]                             only that region (a partial frame)
 *     ['path', contours, FillRule, rgba, clip]            contours: list of lists of [x, y]
 *     ['gradpath', contours, FillRule, gradient, clip]     a path filled with a gradient: ['kind' => 'linear'|'radial', 'inverse' => Affine (a surface point to the gradient's own
 *                                                         space), 'spread' => GradientSpread, 'ramp' => 256 RGBA colours]
 *     ['polyline', points, stroke, closed, rgba, clip]
 *     ['ellipse', cx, cy, rx, ry, rgba, clip]             axis-aligned
 *     ['ring', cx, cy, rx, ry, stroke, rgba, clip]        the band between radii r ± stroke / 2
 *     ['image', Framebuffer, Affine, opacity 1..255, Filter, style, clip]
 *                                                         style: null, or ['tint' => 0xRRGGBBAA, 'window' => [u0, v0, u1, v1]|null (source pixels that paint),
 *                                                         'round' => [x0, y0, x1, y1, [tl, tr, br, bl]]|null (the box whose rounded rect the image is cut to, in pixels)]
 *     ['spans', bytes, rgba, clip]                        whole pixels, already inside the clip, rows top to bottom; 7 bytes each: y, x, length (uint16 LE), coverage 255
 *     ['roundrect', x0, y0, x1, y1, [tl, tr, br, bl], rgba, clip]
 *     ['roundring', x0, y0, x1, y1, [tl, tr, br, bl], inset, rgba, clip]   the band inside the box, inset wide
 *     ['shadow', x0, y0, x1, y1, radius, blur, rgba, clip]                  the box already moved by the caller's offset
 *     ['glass', Region area, [x0, y0, x1, y1, [tl, tr, br, bl]], look, blur, shadow, clip]
 *                                                         shadow: null, or a 'shadow' command painted between the backdrop's capture and the pane
 *                                                         look: [frost, refraction, bevel, saturation, light, grain, tint,
 *                                                         rim, alpha] in device pixels; blur: the radius in device pixels
 *     ['glyphs', TrueTypeFont, pixelSize, glyphs, rgba, Region box, clip]
 *                                                         glyphs: list of [face, glyph, maskLeft, maskTop, style], the mask's
 *                                                         top-left in framebuffer pixels; box: the union of the masks before the clip
 *     ['layer', Region area, [x0, y0, x1, y1, [tl, tr, br, bl]], children, alpha 0..255, FilterChain|null filter, FilterChain|null backdrop, clip]
 *                                                         children: the commands drawn inside, clipped to the area
 */
interface Recorder
{
    /**
     * Open a frame on a surface of this size: the transform is the identity, the clip the whole
     * surface, nothing is pushed.
     */
    public function begin(Region $surface): void;

    /** Whether a frame is open. */
    public function drawing(): bool;

    /** Drop the open frame: nothing of it is kept, and the last finished frame stays the last. */
    public function abandon(): void;

    /**
     * Close the open frame and answer its commands. It becomes the last frame, and the frame that
     * was the last becomes the one drawn() and changed() compare it with.
     *
     * @return list<array<int, mixed>>
     */
    public function finish(): array;

    public function push(): void;

    /** @throws DrawingException Without a push(). */
    public function pop(): void;

    public function translate(float $x, float $y): void;

    public function scale(float $x, ?float $y = null): void;

    public function rotate(float $radians): void;

    public function transform(Affine $matrix): void;

    /** The current transform; the identity between frames. */
    public function matrix(): Affine;

    /** Null is the whole surface. */
    public function clip(?Region $region): void;

    /** The current clip; null between frames. */
    public function clipRegion(): ?Region;

    public function clear(Color $color): void;

    public function fillRect(float $x, float $y, float $width, float $height, Color $color): void;

    public function strokeRect(float $x, float $y, float $width, float $height, Color $color, float $stroke = 1.0): void;

    public function line(float $x0, float $y0, float $x1, float $y1, Color $color, float $stroke = 1.0): void;

    public function polyline(array $points, Color $color, float $stroke = 1.0, bool $closed = false): void;

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, Color $color): void;

    public function fillPolygon(array $points, Color $color, FillRule $rule = FillRule::NON_ZERO): void;

    public function fillPath(array $contours, Color $color, FillRule $rule = FillRule::NON_ZERO): void;

    public function fill(Path $path, Color|Gradient $paint, FillRule $rule = FillRule::NON_ZERO): void;

    public function stroke(Path $path, Color|Gradient $paint, StrokeStyle $style): void;

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color $color): void;

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, Color $color, float $stroke = 1.0): void;

    public function image(Framebuffer $source, float $x, float $y, ?float $width = null, ?float $height = null, float $opacity = 1.0, Filter $filter = Filter::NEAREST, ?array $radii = null, ?Color $tint = null, ?array $sourceRect = null): void;

    public function roundRect(float $x, float $y, float $width, float $height, array $radii, Color $fill, ?Color $border = null, float $borderWidth = 0.0): void;

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, Color $color, float $dx = 0.0, float $dy = 0.0): void;

    /**
     * @param  callable(): void  $draw  Draws the layer's children: every draw call it makes on this recorder is recorded inside the layer.
     */
    public function layer(float $x, float $y, float $width, float $height, callable $draw, float $opacity = 1.0, ?array $radii = null, ?FilterChain $filter = null, ?FilterChain $backdrop = null): void;

    public function glass(float $x, float $y, float $width, float $height, ?array $radii, Glass $glass, float $opacity = 1.0): void;

    public function text(string $text, float $x, float $y, Color $color, Font $font): void;

    /** @return array{float, float, float, float} x, y, width, height of the text's ink from its origin; needs no frame. */
    public function textBounds(string $text, Font $font): array;

    /**
     * Where the last frame draws: every command's box, with $also, merged until no two overlap.
     *
     * @param  Region  $surface  The surface as it is now.
     * @param  list<Region>  $also  Regions that count as drawn whatever the commands say.
     * @return list<Region>
     */
    public function drawn(Region $surface, DamageGranularity $granularity, array $also = []): array;

    /**
     * Where the last frame differs from the one before it. Commands are compared by position: one
     * equal to the command at the same place in the frame before changed nothing; every other
     * command marks its box, and the box of the command it replaced. An image is never equal,
     * because its source may hold new pixels. A layer or pane that reads its backdrop is redrawn
     * whole when anything under its reach is.
     *
     * @param  Region  $surface  The surface as it is now.
     * @param  list<Region>  $also  Regions that count as changed whatever the commands say.
     * @return list<Region> merged until no two overlap; empty when nothing changed
     */
    public function changed(Region $surface, DamageGranularity $granularity, array $also = []): array;

    /**
     * A frame drawn into each region alone: every clip cut to the region, a clear limited to it,
     * spans trimmed to it. Commands that miss a region are left out of it.
     *
     * @param  list<array<int, mixed>>  $commands
     * @param  list<Region>  $regions
     * @return list<array<int, mixed>>
     */
    public function scissor(array $commands, array $regions): array;

    /**
     * The whole pixels a command can write, cut by its clip; null when it writes nothing.
     *
     * @param  array<int, mixed>  $command
     */
    public function box(array $command, Region $surface): ?Region;
}
