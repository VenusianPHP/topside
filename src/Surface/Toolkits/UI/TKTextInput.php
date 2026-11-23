<?php

namespace Surface\Toolkits\UI;

use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;
use Surface\Contracts\Toolkits\Mail\Views\TextSubmitted;
use Surface\Contracts\Toolkits\UI\TKTextInput as PrimitiveContract;
use Surface\Toolkits\UI\Concerns\IgnoresItsOwnWrites;

/**
 * One line of editable text: its value, its placeholder, and whether it is masked.
 *
 * The toolkit's field says what the user did, through typed() and submitted(); the mail is
 * made here, so it is the same on every toolkit.
 */
abstract class TKTextInput extends TKPrimitive implements PrimitiveContract
{
    use IgnoresItsOwnWrites;

    protected TextAlignment $alignment = TextAlignment::LEFT;

    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private string $value = '',
        private ?string $placeholder = null,
        private readonly bool $secret = false,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->guardLive();

        $this->value = $value;
        $this->write(fn () => $this->applyValue($value));

        return $this;
    }

    public function placeholder(): ?string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(?string $placeholder): static
    {
        $this->guardLive();

        $this->placeholder = $placeholder;
        $this->write(fn () => $this->applyPlaceholder($placeholder));

        return $this;
    }

    public function isSecret(): bool
    {
        return $this->secret;
    }

    public function setFont(FontSpec $font): static
    {
        $this->guardLive();
        $this->applyFont($font);

        return $this;
    }

    public function setTextColor(?Color $color): static
    {
        $this->guardLive();
        $this->applyTextColor($color);

        return $this;
    }

    public function setAlignment(TextAlignment $alignment): static
    {
        $this->guardLive();

        $this->applyAlignment($alignment);
        $this->alignment = $alignment;

        return $this;
    }

    /**
     * The field's text is now $value by the user's hand: record it and post TextChanged.
     * Nothing for the value already held (a toolkit may signal one edit twice).
     */
    protected function typed(string $value): void
    {
        if ($value === $this->value || ! $this->hearsUser()) {
            return;
        }

        $this->value = $value;
        $this->window()->post(new TextChanged($this->window()->name(), $this->path(), $this->uuid(), $value));
    }

    /**
     * The user pressed Return on $value.
     */
    protected function submitted(string $value): void
    {
        if (! $this->hearsUser()) {
            return;
        }

        $this->typed($value);
        $this->window()->post(new TextSubmitted($this->window()->name(), $this->path(), $this->uuid(), $this->value));
    }

    abstract protected function applyValue(string $value): void;

    abstract protected function applyPlaceholder(?string $placeholder): void;

    abstract protected function applyFont(FontSpec $font): void;

    /**
     * Null restores the toolkit's own.
     */
    abstract protected function applyTextColor(?Color $color): void;

    abstract protected function applyAlignment(TextAlignment $alignment): void;
}
