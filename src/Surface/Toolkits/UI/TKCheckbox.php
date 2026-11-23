<?php

namespace Surface\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;
use Surface\Contracts\Toolkits\UI\TKCheckbox as PrimitiveContract;

/**
 * A labelled check box: its label, whether it is checked, and the user's toggle.
 *
 * The toolkit's check box tells this one the user toggled it, by calling toggled() from the
 * native's own signal; the mail is made here, so it is the same on every toolkit.
 */
abstract class TKCheckbox extends TKPrimitive implements PrimitiveContract
{
    /**
     * The label and the state are known before the native is built, so it is built with them.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private string $label = '',
        private bool $checked = false,
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

    public function isChecked(): bool
    {
        return $this->checked;
    }

    /**
     * Posts nothing: the app changed it, it knows. The state is stored before the native is
     * told, so a toolkit that signals a change made from code reaches toggled() with the
     * state already held, and toggled() posts nothing for it.
     */
    public function setChecked(bool $checked): static
    {
        $this->guardLive();

        $this->checked = $checked;
        $this->applyChecked($checked);

        return $this;
    }

    /**
     * The native's state changed to $checked: record it and post Toggled. Nothing when it is
     * the state already held (a change made from code), from a check box that is going, or
     * while it or a container above it is disabled.
     */
    protected function toggled(bool $checked): void
    {
        if ($checked === $this->checked || $this->isRemoved() || ! $this->takesInput()) {
            return;
        }

        $this->checked = $checked;
        $this->window()->post(new Toggled($this->window()->name(), $this->path(), $this->uuid(), $checked));
    }

    abstract protected function applyLabel(string $label): void;

    abstract protected function applyChecked(bool $checked): void;
}
