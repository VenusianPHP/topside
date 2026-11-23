<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Panes in order with a draggable divider between each visible pair. A hidden pane collapses and
 * the visible panes share the length; panes keep their minSize().
 */
interface TKSplitView extends TKPrimitiveGroup
{
    /**
     * @return bool true: panes side by side, dividers vertical
     */
    public function isHorizontal(): bool;

    /**
     * Each divider between visible panes as a fraction of the split's length, as the native has it now.
     * @return list<float>
     */
    public function positions(): array;

    /**
     * One fraction, from 0 to 1: there are two panes at most, so one divider. Nest a split for more.
     * @param list<float> $positions
     * @return $this
     */
    public function setPositions(array $positions): static;
}
