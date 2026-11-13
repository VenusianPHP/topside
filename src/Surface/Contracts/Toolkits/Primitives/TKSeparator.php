<?php

namespace Surface\Contracts\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKPrimitive;

/**
 * A horizontal or vertical rule; the orientation is fixed at creation. Posts no mail.
 */
interface TKSeparator extends TKPrimitive
{
    /**
     * @return bool
     */
    public function isHorizontal(): bool;
}
