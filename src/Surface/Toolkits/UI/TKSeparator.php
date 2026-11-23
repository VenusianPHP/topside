<?php

namespace Surface\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\UI\TKSeparator as PrimitiveContract;

/**
 * A line between things: across by default, as between the children of a column.
 */
abstract class TKSeparator extends TKPrimitive implements PrimitiveContract
{
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private readonly bool $horizontal = true,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function isHorizontal(): bool
    {
        return $this->horizontal;
    }
}
