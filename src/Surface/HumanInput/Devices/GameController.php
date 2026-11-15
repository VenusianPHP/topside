<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\GamepadAxis;

/** A game pad with sticks and triggers. Axes are levels: no edges, so every reader reads them alike. */
class GameController extends GamePad
{
    /** @var array<string, float> by GamepadAxis->value */
    public array $axes = [];

    public function setAxis(GamepadAxis $axis, float $value): void
    {
        $this->axes[$axis->value] = in_array($axis, [GamepadAxis::LEFT_TRIGGER, GamepadAxis::RIGHT_TRIGGER], true)
            ? max(0.0, min(1.0, $value))
            : max(-1.0, min(1.0, $value));
    }

    public function releaseAll(?int $at_ns = null): void
    {
        parent::releaseAll($at_ns);
        $this->axes = [];
    }
}
