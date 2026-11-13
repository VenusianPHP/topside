<?php

namespace Surface\Contracts\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKPrimitiveGroup;

/**
 * Children stacked in display order left to right; children reorder with moveBefore()/moveAfter()/moveTo().
 */
interface TKRow extends TKPrimitiveGroup
{
    /**
     * @return int
     */
    public function spacing(): int;

    /**
     * @param int $spacing
     * @return $this
     */
    public function setSpacing(int $spacing): static;
}
