<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\Devices\GameController as GameControllerContract;
use Surface\Contracts\HumanInput\GamepadAxis;

/** A game controller as one stage sees it. */
class ControllerView extends PadView implements GameControllerContract
{
    public function __construct(GameController $pad)
    {
        parent::__construct($pad);
    }

    public function axes(): array
    {
        return array_map(fn (string $axis): GamepadAxis => GamepadAxis::from($axis), array_keys($this->controller()->axes));
    }

    public function axis(GamepadAxis $axis): float
    {
        return $this->controller()->axes[$axis->value] ?? 0.0;
    }

    public function leftStick(): array
    {
        return ['x' => $this->axis(GamepadAxis::LEFT_X), 'y' => $this->axis(GamepadAxis::LEFT_Y)];
    }

    public function rightStick(): array
    {
        return ['x' => $this->axis(GamepadAxis::RIGHT_X), 'y' => $this->axis(GamepadAxis::RIGHT_Y)];
    }

    public function leftTrigger(): float
    {
        return $this->axis(GamepadAxis::LEFT_TRIGGER);
    }

    public function rightTrigger(): float
    {
        return $this->axis(GamepadAxis::RIGHT_TRIGGER);
    }

    private function controller(): GameController
    {
        /** @var GameController */
        return $this->pad;
    }
}
