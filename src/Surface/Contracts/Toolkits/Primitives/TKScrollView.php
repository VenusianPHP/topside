<?php

namespace Surface\Contracts\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKPrimitiveGroup;

/**
 * Scrolls exactly one container (column, row, grid or fixed). A leaf, or a second
 * container, throws ToolkitRuntimeException.
 */
interface TKScrollView extends TKPrimitiveGroup
{
    /**
     * @param bool $horizontal
     * @param bool $vertical
     * @return $this
     */
    public function setScrollbars(bool $horizontal, bool $vertical): static;

    /**
     * The scrolled container, once created.
     * @return TKPrimitiveGroup|null
     */
    public function content(): ?TKPrimitiveGroup;
}
