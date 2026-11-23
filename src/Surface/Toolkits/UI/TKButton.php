<?php

namespace Surface\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\ButtonClicked;
use Surface\Contracts\Toolkits\UI\TKButton as PrimitiveContract;

/**
 * A push button: its label, and its click.
 *
 * The toolkit's button tells this one it was clicked, by calling clicked() from the
 * native's own click signal; the mail is made here, so it is the same on every toolkit.
 */
abstract class TKButton extends TKPrimitive implements PrimitiveContract
{
    /**
     * The label is known before the native is built, so it is built with it.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private string $label = '',
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

    /**
     * The native was clicked: post ButtonClicked. Nothing while the button or a container
     * above it is disabled (a toolkit blocks the user's clicks there, but not every toolkit
     * blocks a click made from code), and nothing from a button that is going (removed, its
     * native not yet destroyed).
     */
    protected function clicked(): void
    {
        if ($this->isRemoved() || ! $this->takesInput()) {
            return;
        }

        $this->window()->post(new ButtonClicked($this->window()->name(), $this->path(), $this->uuid()));
    }

    abstract protected function applyLabel(string $label): void;
}
