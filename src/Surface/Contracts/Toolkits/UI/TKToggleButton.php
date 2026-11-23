<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitive;

/**
 * A button that stays pressed. Posts Toggled.
 *
 * As with TKButton, its image (HasButtonImage) comes back when it is needed.
 */
interface TKToggleButton extends TKPrimitive
{
    /**
     * @return string
     */
    public function label(): string;

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): static;

    /**
     * @return bool
     */
    public function isPressed(): bool;

    /**
     * Posts nothing: the app changed it, it knows.
     * @param bool $pressed
     * @return $this
     */
    public function setPressed(bool $pressed): static;
}
