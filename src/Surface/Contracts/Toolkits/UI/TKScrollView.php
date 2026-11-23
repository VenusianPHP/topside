<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Scrolls exactly one container (column, row, grid or fixed). A leaf, or a second
 * container, throws RuntimeException.
 */
interface TKScrollView extends TKPrimitiveGroup
{
    /**
     * @param bool $horizontal
     * @param bool $vertical
     * @return $this
     * @throws \RuntimeException With neither: it scrolls one way or both.
     */
    public function setScrollbars(bool $horizontal, bool $vertical): static;

    /**
     * The scrolled container, once created.
     * @return TKPrimitiveGroup|null
     */
    public function content(): ?TKPrimitiveGroup;
}
