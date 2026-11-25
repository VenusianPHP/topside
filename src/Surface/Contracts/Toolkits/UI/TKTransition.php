<?php

namespace Surface\Contracts\Toolkits\UI;

/**
 * How a primitive appears or disappears when setVisible() changes it (TKPrimitive::appear(), disappear()).
 * A fade; a fade with a slide from SLIDE_DISTANCE points away; or a move from an edge by the
 * primitive's own full size, with no fade (SwiftUI's move(edge:), an off-canvas drawer's
 * translateX(100%)). The toolkit runs the animation; nothing steps it from PHP.
 */
enum TKTransition: string
{
    /** Points a slide starts from (appearing) or ends at (disappearing). */
    public const int SLIDE_DISTANCE = 24;

    /**
     * The cubic Bézier a move follows as it appears (x1, y1, x2, y2, as CSS cubic-bezier()
     * writes it): easeOutQuint, quick at first and settling softly at its place.
     */
    public const array MOVE_IN_CURVE = [0.22, 1.0, 0.36, 1.0];

    /**
     * The curve a move follows as it disappears: MOVE_IN_CURVE mirrored (easeInQuint), so
     * leaving is the same motion played backwards, slow at first and fast out.
     */
    public const array MOVE_OUT_CURVE = [0.64, 0.0, 0.78, 0.0];

    /** How long a transition runs when appear() or disappear() is given no time. */
    public const float DEFAULT_SECONDS = 0.3;

    case FADE = 'fade';
    case SLIDE_UP = 'slide-up';
    case SLIDE_DOWN = 'slide-down';
    case SLIDE_LEFT = 'slide-left';
    case SLIDE_RIGHT = 'slide-right';

    /** In from the right edge by its own width, and back out to it. */
    case MOVE_RIGHT = 'move-right';

    /** In from the left edge by its own width, and back out to it. */
    case MOVE_LEFT = 'move-left';

    /** In from the top edge by its own height, and back out to it. */
    case MOVE_TOP = 'move-top';

    /** In from the bottom edge by its own height, and back out to it. */
    case MOVE_BOTTOM = 'move-bottom';

    /**
     * Where the view is drawn from at the start of an appearance, in points, top-left based
     * (SLIDE_UP comes from below, so its y offset is positive). A move is measured in the
     * view's own size instead (edge()), so its offset is 0, 0.
     *
     * @return array{int, int} x, y
     */
    public function offset(): array
    {
        return match ($this) {
            self::MOVE_RIGHT, self::MOVE_LEFT, self::MOVE_TOP, self::MOVE_BOTTOM => [0, 0],
            self::FADE => [0, 0],
            self::SLIDE_UP => [0, self::SLIDE_DISTANCE],
            self::SLIDE_DOWN => [0, -self::SLIDE_DISTANCE],
            self::SLIDE_LEFT => [self::SLIDE_DISTANCE, 0],
            self::SLIDE_RIGHT => [-self::SLIDE_DISTANCE, 0],
        };
    }

    /**
     * A move: travels the view's own full width or height from an edge, with no fade.
     */
    public function moves(): bool
    {
        return ! is_null($this->edge());
    }

    /**
     * For a move, where the view starts at the beginning of an appearance in its own size,
     * top-left based: MOVE_RIGHT is [1, 0], one width to the right. Null for a fade or slide.
     *
     * @return array{int, int}|null
     */
    public function edge(): ?array
    {
        return match ($this) {
            self::MOVE_RIGHT => [1, 0],
            self::MOVE_LEFT => [-1, 0],
            self::MOVE_TOP => [0, -1],
            self::MOVE_BOTTOM => [0, 1],
            default => null,
        };
    }

    /**
     * For a move, the cubic Bézier it follows (x1, y1, x2, y2): MOVE_IN_CURVE appearing,
     * MOVE_OUT_CURVE disappearing. Null for a fade or slide, which keep the toolkit's own.
     *
     * @return array{float, float, float, float}|null
     */
    public function curve(bool $appearing): ?array
    {
        if (! $this->moves()) {
            return null;
        }

        return $appearing ? self::MOVE_IN_CURVE : self::MOVE_OUT_CURVE;
    }
}
