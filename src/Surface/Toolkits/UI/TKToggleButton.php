<?php

namespace Surface\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;
use Surface\Contracts\Toolkits\UI\TKToggleButton as PrimitiveContract;

/**
 * A button that stays pressed. The toolkit's button calls toggled() from the native's own
 * signal; the mail is made here, so it is the same on every toolkit.
 */
abstract class TKToggleButton extends TKPrimitive implements PrimitiveContract
{
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private string $label = '',
        private bool $pressed = false,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->guardLive();

        $this->label = $label;
        $this->applyLabel($label);

        return $this;
    }

    public function isPressed(): bool
    {
        return $this->pressed;
    }

    /**
     * Posts nothing: the app changed it, it knows. Stored before the native is told, so a
     * toolkit that signals it reaches toggled() with the state already held.
     */
    public function setPressed(bool $pressed): static
    {
        $this->guardLive();

        $this->pressed = $pressed;
        $this->applyPressed($pressed);

        return $this;
    }

    /**
     * The user pressed it in or out: record it and post Toggled. Nothing when it is the state
     * already held, or from a button that is going or takes no input.
     */
    protected function toggled(bool $pressed): void
    {
        if ($pressed === $this->pressed || $this->isRemoved() || ! $this->takesInput()) {
            return;
        }

        $this->pressed = $pressed;
        $this->window()->post(new Toggled($this->window()->name(), $this->path(), $this->uuid(), $pressed));
    }

    abstract protected function applyLabel(string $label): void;

    abstract protected function applyPressed(bool $pressed): void;
}
