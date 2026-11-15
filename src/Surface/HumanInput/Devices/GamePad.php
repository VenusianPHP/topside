<?php

namespace Surface\HumanInput\Devices;

use Closure;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\HumanInputException;

/**
 * One game pad, as its pad source reports it. It belongs to the process, not to a stage: stages
 * read it through a PadView each, which keeps that stage's edges.
 */
class GamePad
{
    /** @var array<string, DigitalButton> by GamepadButton->value, one for each button it has */
    public array $buttons = [];

    /**
     * @param list<GamepadButton> $buttons the buttons the pad has
     * @param Closure(?int): void|null $lights lights the pad's player LEDs for an index; null: it has none
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        array $buttons,
        public readonly ?string $hardwareId = null,
        private readonly ?Closure $lights = null,
    ) {
        foreach ($buttons as $button) {
            $this->buttons[$button->value] = new DigitalButton($button->value);
        }
    }

    /** @throws HumanInputException For a button the pad does not have. */
    public function update(GamepadButton $button, bool $down, ?int $at_ns = null): void
    {
        $known = $this->buttons[$button->value] ?? throw HumanInputException::unsupportedButton($this->name, $button);
        $known->update($down, $at_ns);
    }

    /** The pad went quiet (its app lost focus, it is going): every held button goes up. */
    public function releaseAll(?int $at_ns = null): void
    {
        foreach ($this->buttons as $button) {
            $button->update(false, $at_ns);
        }
    }

    public function setPlayerIndex(?int $index): void
    {
        if (! is_null($this->lights)) {
            ($this->lights)($index);
        }
    }
}
