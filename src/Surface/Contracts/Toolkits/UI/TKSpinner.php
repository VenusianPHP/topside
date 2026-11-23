<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitive;

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
