<?php

namespace Surface\Contracts\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKItemView;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

/**
 * Items in one column of equal-height rows. Items are ListRow. A double click, Enter or Return
 * activates a row.
 */
interface TKListView extends TKItemView
{
    public function rowHeight(): int;

    /**
     * @return $this
     * @throws ToolkitRuntimeException When the height is < 1.
     */
    public function setRowHeight(int $height): static;
}
