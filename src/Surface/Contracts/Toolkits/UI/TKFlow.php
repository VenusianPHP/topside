<?php

namespace Surface\Contracts\Toolkits\UI;

use RuntimeException;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Children side by side in display order, each at its own size, wrapping onto the next line
 * where the width runs out: tags, chips, a row of buttons that must not be cut. Lines start at
 * the left edge. Children reorder with moveBefore()/moveAfter()/moveTo().
 */
interface TKFlow extends TKPrimitiveGroup
{
    /**
     * Points between one child and the next on a line, and between one line and the next.
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
