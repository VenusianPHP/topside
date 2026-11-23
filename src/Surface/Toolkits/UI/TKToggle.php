<?php

namespace Surface\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;
use Surface\Contracts\Toolkits\UI\TKToggle as PrimitiveContract;

/**
 * An on/off switch: whether it is on, and the user's flip.
 *
 * The toolkit's switch tells this one the user flipped it, by calling toggled() from the
 * native's own signal; the mail is made here, so it is the same on every toolkit.
 */
abstract class TKToggle extends TKPrimitive implements PrimitiveContract
{
    /**
     * The state is known before the native is built, so it is built with it.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private bool $on = false,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function isOn(): bool
    {
        return $this->on;
    }

    /**
     * Posts nothing: the app changed it, it knows. Stored before the native is told, as
     * TKCheckbox::setChecked().
     */
    public function setOn(bool $on): static
    {
        $this->guardLive();

        $this->on = $on;
        $this->applyOn($on);

        return $this;
    }

    /**
     * The native's state changed to $on: record it and post Toggled. Nothing when it is the
     * state already held, from a switch that is going, or while it or a container above it
     * is disabled.
     */
    protected function toggled(bool $on): void
    {
        if ($on === $this->on || $this->isRemoved() || ! $this->takesInput()) {
            return;
        }

        $this->on = $on;
        $this->window()->post(new Toggled($this->window()->name(), $this->path(), $this->uuid(), $on));
    }

    abstract protected function applyOn(bool $on): void;
}
