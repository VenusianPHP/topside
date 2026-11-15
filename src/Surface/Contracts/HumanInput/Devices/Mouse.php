<?php

namespace Surface\Contracts\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\HumanInput\ScrollPhase;

/**
 * What a stage reads to ask about the pointer this frame: a mouse, a Magic Mouse or a trackpad,
 * read alike. Positions and motion are in the stage's pixels, from its top left.
 *
 * Scrolling is read as the device reports it, whatever the device is:
 * - precise() false: a wheel with notches; wheel() is in notches.
 * - precise() true: a device that scrolls by distance (a trackpad, a Magic Mouse, a free-spinning
 *   wheel); wheel() is in the stage's pixels.
 * In both, dy > 0 scrolls the content down (the wheel rolled toward the user, or fingers moved up
 * with natural scrolling on). inverted() says the user has natural scrolling on, for code that
 * wants the fingers' direction instead. phase() says where the scroll sits in a gesture; a device
 * that reports none reads NONE.
 */
interface Mouse
{
    public function x(): float;

    public function y(): float;

    /** Whether the pointer is over the stage. x() and y() stay where it left. */
    public function inside(): bool;

    /** @return array{dx: float, dy: float} motion since the last frame */
    public function motion(): array;

    /** @return array{dx: float, dy: float} scrolling since the last frame */
    public function wheel(): array;

    public function precise(): bool;

    public function inverted(): bool;

    public function phase(): ScrollPhase;

    public function button(MouseButton $button): ButtonState;

    public function isDown(MouseButton $button): bool;

    public function isPressed(MouseButton $button): bool;

    public function wasReleased(MouseButton $button): bool;

    public function isHolding(MouseButton $button, int $hold_ms): bool;
}
