<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Children stacked in display order top to bottom; children reorder with moveBefore()/moveAfter()/moveTo().
 */
interface TKColumn extends TKPrimitiveGroup
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

    /**
     * Space between the edge and the children.
     * @return Insets
     */
    public function padding(): Insets;

    /**
     * @param Insets|int|string|list<int> $padding one to four values, as CSS takes them
     * @return $this
     * @throws RuntimeException When a side is negative.
     */
    public function setPadding(Insets|int|string|array $padding): static;
}
