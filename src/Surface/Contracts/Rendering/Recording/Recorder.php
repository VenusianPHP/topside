<?php

namespace Surface\Contracts\Rendering\Recording;

use Surface\Contracts\Rendering\Enums\FillRule;

use Surface\Contracts\Rendering\Recording\Enums\CommandReach;

/**
 * A frame of drawing commands, recorded under a transform and a clip, and what changed since the frame before.
 * It draws nothing. Every point goes through the current transform when its shape is recorded, so a command holds
 * frame pixels. Commands are lists: [kind, ...]. A path command carries FillRule::NON_ZERO or FillRule::EVEN_ODD.
 *
 * A shape's paint is an int colour, 0xRRGGBBAA not multiplied by its alpha, or a gradient: a list of four, [kind,
 * geometry, stops, spread], with kind 'linear' (geometry [x0, y0, x1, y1]) or 'radial' (geometry [cx, cy, r]), stops
 * [[offset, 0xRRGGBBAA], ...] and spread 'pad', 'repeat' or 'reflect', in the units the shape is given in. A gradient
 * is recorded as given with the transform it is drawn through appended, [a, b, c, d, e, f]: drawers take it through
 * that to pixels. Neither recorder reads inside a gradient.
 */
interface Recorder
{
    /** Opens a frame width by height: no commands, the identity transform, nothing pushed, the clip the whole frame. */
    public function begin(int $width, int $height, mixed $clip): void;

    /** Whether a frame is open. */
    public function recording(): bool;

    /** Drops the open frame, its layers and its pushes. The finished frames stay. */
    public function abandon(): void;

    /**
     * Closes the frame: it becomes the last frame, and the last frame becomes the one before.
     *
     * @return list<array> its commands, in draw order
     */
    public function finish(): array;

    /** Keeps the transform and the clip. */
    public function push(): void;

    /** Brings back what the last push() kept. */
    public function pop(): void;

    /** How many pushes wait in the frame or layer being recorded. */
    public function depth(): int;

    /** The current transform times this one. */
    public function concat(float $a, float $b, float $c, float $d, float $e, float $f): void;

    /** @return array{float, float, float, float, float, float} the current transform, [a, b, c, d, e, f] */
    public function matrix(): array;

    /** Sets the clip: its rect, and the value every command recorded under it carries. */
    public function clip(mixed $clip, int $x, int $y, int $width, int $height): void;

    /** The current clip's value. */
    public function clipValue(): mixed;

    /** Starts a layer: commands go to a list of their own, under this clip. */
    public function enter(mixed $clip, int $x, int $y, int $width, int $height): void;

    /** @return list<array> the layer's commands; the frame's commands, clip and pushes come back */
    public function leave(): array;

    /** ['clear', rgba] */
    public function clear(int $rgba): void;

    /** ['path', [[tl, tr, br, bl]], FillRule::NON_ZERO, rgba, clip] */
    public function fillRect(float $x, float $y, float $width, float $height, int|array $rgba): void;

    /** ['polyline', [tl, tr, br, bl], stroke, true, rgba, clip] */
    public function strokeRect(float $x, float $y, float $width, float $height, int|array $rgba, float $stroke): void;

    /** ['polyline', [p0, p1], stroke, false, rgba, clip] */
    public function line(float $x0, float $y0, float $x1, float $y1, int|array $rgba, float $stroke): void;

    /** ['path', [[p0, p1, p2]], FillRule::NON_ZERO, rgba, clip] */
    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, int|array $rgba): void;

    /**
     * ['path', contours, rule, rgba, clip] of every subpath of $ops, through the transform, curves flattened to within
     * a quarter pixel; nothing for no points.
     *
     * @param list<array> $ops ['M', x, y], ['L', x, y], ['Q', cx, cy, x, y], ['C', c1x, c1y, c2x, c2y, x, y], ['Z']
     */
    public function fillPath(array $ops, FillRule $rule, int|array $rgba): void;

    /**
     * ['polyline', points, stroke, closed, rgba, clip] for every subpath of two points or more, flattened as
     * fillPath()'s, closed where a 'Z' closes it, the stroke scaled as line()'s.
     *
     * @param list<array> $ops as fillPath()'s
     */
    public function strokePath(array $ops, int|array $rgba, float $stroke): void;

    /** ['ellipse', cx, cy, rx, ry, rgba, clip], or a 'path' of it under a turn or a shear */
    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, int|array $rgba): void;

    /** ['ring', cx, cy, rx, ry, stroke, rgba, clip], or a closed 'polyline' of it under a turn or a shear */
    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, int|array $rgba, float $stroke): void;

    /**
     * 'roundrect' and 'roundring' commands, or 'path' commands under a transform that is not even.
     *
     * @param array{int|float, int|float, int|float, int|float} $radii top-left, top-right, bottom-right, bottom-left
     */
    public function roundRect(float $x, float $y, float $width, float $height, array $radii, int|array $fill, int|array|null $border, float $borderWidth): void;

    /** ['shadow', x0, y0, x1, y1, radius, blur, rgba, clip]; needs an even transform */
    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, int $rgba, float $dx, float $dy): void;

    /**
     * ['glyphs', runs, [a, b, c, d, e, f], rgba, [x0, y0, x1, y1], clip]; nothing for a null ink.
     *
     * @param array{int|float, int|float, int|float, int|float}|null $ink the text's box, [x0, y0, x1, y1]
     */
    public function glyphs(array $runs, float $x, float $y, ?array $ink, int $rgba): void;

    /**
     * A command made elsewhere, recorded as it is. $how says where it draws.
     *
     * @param list<int|float>|null $pieces for CommandReach::PIECES: x0, y0, x1, y1 then a clip's x, y, width, height; 8 numbers, or 16 for two
     * @param bool $restless its pixels can change with nothing in it changing
     * @param list<int|float>|null $reads [x, y, width, height, reach]: it reads the pixels under that rect, and reach around it
     */
    public function append(array $command, CommandReach $how, float $grow = 0.0, ?array $pieces = null, bool $restless = false, ?array $reads = null): void;

    /**
     * A recorded command in a simpler form, for a drawer whose library cannot draw it as it is:
     *   'polygon'     ['path', contours, rule, paint, clip]: curves as straight sides, a stroke as the outline it covers
     *                 (butt ends, miters up to 4, bevels past it)
     *   'trapezoids'  ['trapezoids', [[top, bottom, left at top, left at bottom, right at top, right at bottom], ...], paint, clip]
     *   'triangles'   ['triangles', [[x0, y0, x1, y1, x2, y2], ...], paint, clip]
     * Each form can be lowered from the one before: a 'trapezoids' command lowers to 'triangles' and to nothing else.
     * null for a command with no outline ('clear', 'shadow', 'glyphs'). Reads nothing but the command.
     *
     * $packing gives a 'trapezoids' or 'triangles' command's items as packed strings, laid out as a library's own struct
     * array so a drawer hands them to its library as they are: a list of [encoding, fields] pairs, one string each.
     * encoding is 'f32' (little-endian float32) or 'fixed16' (little-endian int32, each number times 65536 rounded half
     * away from zero); fields are the places in an item (0 to 5) the struct's fields hold, in order, repeats allowed.
     * The command's items are then the list of those strings. A 'polygon' is always lists; a packed command lowers no
     * further.
     *
     * @param list<array{string, list<int>}>|null $packing
     *
     * @throws \ValueError for a form not named here, or a packing not as described
     */
    public function lower(array $command, string $form, ?array $packing = null): ?array;

    /**
     * Where the last frame draws, merged so no two rects overlap.
     *
     * @param list<int> $also more rects to merge in, four integers each
     * @return list<int> x, y, width, height, four integers a rect
     */
    public function drawn(int $surfaceWidth, int $surfaceHeight, int $unitWidth, int $unitHeight, int $limitWidth, int $limitHeight, array $also = []): array;

    /**
     * Where the last frame differs from the one before, merged so no two rects overlap.
     *
     * @param list<int> $also more rects to merge in, four integers each
     * @return list<int> x, y, width, height, four integers a rect
     */
    public function changed(int $surfaceWidth, int $surfaceHeight, int $unitWidth, int $unitHeight, int $limitWidth, int $limitHeight, array $also = []): array;
}
