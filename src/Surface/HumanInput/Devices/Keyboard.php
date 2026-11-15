<?php

namespace Surface\HumanInput\Devices;

use Closure;
use Surface\Contracts\HumanInput\Devices\Keyboard as KeyboardContract;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;

/** One stage's keyboard. Its backend reports to it; the stage's frames read it. */
class Keyboard implements KeyboardContract
{
    /** @var array<string, DigitalButton> by Key->value */
    protected array $keys = [];

    protected string $text = '';

    protected Modifiers $modifiers;

    protected Seen $seen;

    public function __construct()
    {
        $this->modifiers = new Modifiers();
        $this->seen = new Seen();
    }

    public function update(Key $key, bool $down, ?int $at_ns = null): void
    {
        ($this->keys[$key->value] ??= new DigitalButton($key->value))->update($down, $at_ns);
    }

    public function appendText(string $text): void
    {
        $this->text .= $text;
    }

    public function setModifiers(Modifiers $modifiers): void
    {
        $this->modifiers = $modifiers;
    }

    /** The stage lost the keyboard: every held key goes up, with its release edge, and so do the modifiers. */
    public function releaseAll(?int $at_ns = null): void
    {
        foreach ($this->keys as $button) {
            $button->update(false, $at_ns);
        }
        $this->modifiers = new Modifiers();
    }

    public function settle(): void
    {
        $this->seen->mark($this->keys);
        $this->text = '';
    }

    public function isDown(Key $key): bool
    {
        return $this->view($key)->isDown();
    }

    public function isPressed(Key $key): bool
    {
        return $this->view($key)->isPressed();
    }

    public function wasReleased(Key $key): bool
    {
        return $this->view($key)->wasReleased();
    }

    public function isHolding(Key $key, int $hold_ms): bool
    {
        return $this->view($key)->isHolding($hold_ms);
    }

    public function downKeys(): array
    {
        return $this->keysWhere(fn (DigitalButton $b): bool => $b->down);
    }

    public function pressedKeys(): array
    {
        return $this->keysWhere(fn (DigitalButton $b): bool => $this->seen->pressed($b));
    }

    public function releasedKeys(): array
    {
        return $this->keysWhere(fn (DigitalButton $b): bool => $this->seen->released($b));
    }

    public function text(): string
    {
        return $this->text;
    }

    public function modifiers(): Modifiers
    {
        return $this->modifiers;
    }

    protected function view(Key $key): ButtonView
    {
        return new ButtonView($this->keys[$key->value] ?? null, $this->seen);
    }

    /** @return list<Key> */
    private function keysWhere(Closure $test): array
    {
        return array_values(array_map(
            fn (DigitalButton $b): Key => Key::from($b->name),
            array_filter($this->keys, $test),
        ));
    }
}
