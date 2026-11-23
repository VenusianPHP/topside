<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitive;

/**
 * An on/off switch. Posts Toggled.
 */
interface TKToggle extends TKPrimitive
{
    /**
     * @return bool
     */
    public function isOn(): bool;

    /**
     * Posts nothing: the app changed it, it knows.
     * @param bool $on
     * @return $this
     */
    public function setOn(bool $on): static;
}
