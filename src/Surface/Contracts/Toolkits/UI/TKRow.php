<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

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
