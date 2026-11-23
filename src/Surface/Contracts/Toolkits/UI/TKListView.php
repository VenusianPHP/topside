<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKItemView;
use RuntimeException;

/**
 * Items in one column of equal-height rows. Items are ListRow. A double click, Enter or Return
 * activates a row.
 */
interface TKListView extends TKItemView
{
    public function rowHeight(): int;

    /**
     * @return $this
     * @throws RuntimeException When the height is < 1.
     */
    public function setRowHeight(int $height): static;
}
