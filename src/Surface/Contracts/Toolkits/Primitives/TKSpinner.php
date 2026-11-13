<?php

namespace Surface\Contracts\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKPrimitive;

/**
 * An indeterminate activity indicator. Posts no mail.
 */
interface TKSpinner extends TKPrimitive
{
    /**
     * @return $this
     */
    public function start(): static;

    /**
     * @return $this
     */
    public function stop(): static;

    /**
     * @return bool
     */
    public function isSpinning(): bool;
}
