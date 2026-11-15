<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;

/** One button as one reader sees it. A button never reported reads all up. */
final readonly class ButtonView implements ButtonState
{
    public function __construct(
        private ?DigitalButton $button,
        private Seen $seen,
    ) {}

    public function isDown(): bool
    {
        return $this->button?->down ?? false;
    }

    /** Went down since the reader's last settle, even if it is up again. */
    public function isPressed(): bool
    {
        return ! is_null($this->button) && $this->seen->pressed($this->button);
    }

    /** Went up since the reader's last settle, even if it is down again. */
    public function wasReleased(): bool
    {
        return ! is_null($this->button) && $this->seen->released($this->button);
    }

    public function heldMs(): int
    {
        if (! $this->isDown()) {
            return 0;
        }

        return intdiv(hrtime(true) - $this->button->changedAt, 1_000_000);
    }

    public function isHolding(int $hold_ms): bool
    {
        return $this->isDown() && $this->heldMs() >= $hold_ms;
    }
}
