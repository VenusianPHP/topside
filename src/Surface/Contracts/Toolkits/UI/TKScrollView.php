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

    /**
     * Where the view is: the visible rect as fractions of the scrolled container.
     */
    public function scrollPosition(): \Surface\Contracts\Toolkits\Styling\ScrollPosition;

    /**
     * While on, the user's scrolls post Mail\Views\ViewScrolled, the latest per pump.
     *
     * @return $this
     */
    public function watchScroll(bool $on = true): static;

    public function isWatchingScroll(): bool;
}
