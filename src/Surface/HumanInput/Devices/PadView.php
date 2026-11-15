<?php

namespace Surface\HumanInput\Devices;

use Closure;
use Surface\Contracts\HumanInput\ButtonState;
use Surface\Contracts\HumanInput\Devices\GamePad as GamePadContract;
use Surface\Contracts\HumanInput\GamepadButton;

/** One pad as one stage sees it: the pad's state, with this stage's own edges. */
class PadView implements GamePadContract
{
    protected Seen $seen;

    public function __construct(
        protected readonly GamePad $pad,
    ) {
        $this->seen = new Seen();
    }

    /** The stage's frame is over: what it read of the pad is seen. */
    public function settle(): void
    {
        $this->seen->mark($this->pad->buttons);
    }

    public function id(): string
    {
        return $this->pad->id;
    }

    public function name(): string
    {
        return $this->pad->name;
    }

    public function supports(GamepadButton $button): bool
    {
        return isset($this->pad->buttons[$button->value]);
    }

    public function button(GamepadButton $button): ButtonState
    {
        return new ButtonView($this->pad->buttons[$button->value] ?? null, $this->seen);
    }

    public function isDown(GamepadButton $button): bool
    {
        return $this->button($button)->isDown();
    }

    public function isPressed(GamepadButton $button): bool
    {
        return $this->button($button)->isPressed();
    }

    public function wasReleased(GamepadButton $button): bool
    {
        return $this->button($button)->wasReleased();
    }

    public function isHolding(GamepadButton $button, int $hold_ms): bool
    {
        return $this->button($button)->isHolding($hold_ms);
    }

    public function downButtons(): array
    {
        return $this->buttonsWhere(fn (DigitalButton $b): bool => $b->down);
    }

    public function pressedButtons(): array
    {
        return $this->buttonsWhere(fn (DigitalButton $b): bool => $this->seen->pressed($b));
    }

    public function hardwareId(): ?string
    {
        return $this->pad->hardwareId;
    }

    public function setPlayerIndex(?int $index): void
    {
        $this->pad->setPlayerIndex($index);
    }

    /** @return list<GamepadButton> */
    private function buttonsWhere(Closure $test): array
    {
        return array_values(array_map(
            fn (DigitalButton $b): GamepadButton => GamepadButton::from($b->name),
            array_filter($this->pad->buttons, $test),
        ));
    }
}
