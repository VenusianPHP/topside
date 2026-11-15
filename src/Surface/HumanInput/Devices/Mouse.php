<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;
use Surface\Contracts\HumanInput\Devices\Mouse as MouseContract;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\HumanInput\ScrollPhase;

/**
 * One stage's pointer: a mouse, a Magic Mouse or a trackpad, read alike. Its backend reports to it;
 * the stage's frames read it. A backend whose library says more about the device extends it.
 *
 * Scrolling adds up until settle(). A frame's precise(), inverted() and phase() are the last
 * scroll's: a frame that mixed a notched wheel and a trackpad reads as the last one.
 */
class Mouse implements MouseContract
{
    protected float $x = 0.0;

    protected float $y = 0.0;

    protected bool $inside = false;

    protected float $dx = 0.0;

    protected float $dy = 0.0;

    protected float $wheelX = 0.0;

    protected float $wheelY = 0.0;

    protected bool $precise = false;

    protected bool $inverted = false;

    protected ScrollPhase $phase = ScrollPhase::NONE;

    /** @var array<string, DigitalButton> by MouseButton->value */
    protected array $buttons = [];

    protected Seen $seen;

    public function __construct()
    {
        $this->seen = new Seen();
    }

    /** Where the pointer is, in the stage's pixels, and whether it is over the stage. */
    public function moveTo(float $x, float $y, bool $inside = true): void
    {
        $this->x = $x;
        $this->y = $y;
        $this->inside = $inside;
    }

    public function addMotion(float $dx, float $dy): void
    {
        $this->dx += $dx;
        $this->dy += $dy;
    }

    /** One scroll, as the device reported it: see Devices\Mouse for the units and directions. */
    public function addScroll(float $dx, float $dy, bool $precise, bool $inverted = false, ScrollPhase $phase = ScrollPhase::NONE): void
    {
        $this->wheelX += $dx;
        $this->wheelY += $dy;
        $this->precise = $precise;
        $this->inverted = $inverted;
        $this->phase = $phase;
    }

    public function update(MouseButton $button, bool $down, ?int $at_ns = null): void
    {
        ($this->buttons[$button->value] ??= new DigitalButton($button->value))->update($down, $at_ns);
    }

    /** The stage lost the pointer's buttons: every held one goes up, with its release edge. */
    public function releaseAll(?int $at_ns = null): void
    {
        foreach ($this->buttons as $button) {
            $button->update(false, $at_ns);
        }
    }

    public function settle(): void
    {
        $this->seen->mark($this->buttons);
        $this->dx = $this->dy = $this->wheelX = $this->wheelY = 0.0;
        // a gesture still under way is still in its phase; a finished one is over
        if (in_array($this->phase, [ScrollPhase::ENDED, ScrollPhase::MOMENTUM_ENDED], true)) {
            $this->phase = ScrollPhase::NONE;
        }
    }

    public function x(): float
    {
        return $this->x;
    }

    public function y(): float
    {
        return $this->y;
    }

    public function inside(): bool
    {
        return $this->inside;
    }

    public function motion(): array
    {
        return ['dx' => $this->dx, 'dy' => $this->dy];
    }

    public function wheel(): array
    {
        return ['dx' => $this->wheelX, 'dy' => $this->wheelY];
    }

    public function precise(): bool
    {
        return $this->precise;
    }

    public function inverted(): bool
    {
        return $this->inverted;
    }

    public function phase(): ScrollPhase
    {
        return $this->phase;
    }

    public function button(MouseButton $button): ButtonState
    {
        return new ButtonView($this->buttons[$button->value] ?? null, $this->seen);
    }

    public function isDown(MouseButton $button): bool
    {
        return $this->button($button)->isDown();
    }

    public function isPressed(MouseButton $button): bool
    {
        return $this->button($button)->isPressed();
    }

    public function wasReleased(MouseButton $button): bool
    {
        return $this->button($button)->wasReleased();
    }

    public function isHolding(MouseButton $button, int $hold_ms): bool
    {
        return $this->button($button)->isHolding($hold_ms);
    }
}
