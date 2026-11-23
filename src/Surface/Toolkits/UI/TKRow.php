<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\UI\Concerns\LinesUpChildren;
use Surface\Contracts\Toolkits\UI\TKRow as PrimitiveContract;

/**
 * Children side by side, in display order. A child reorders itself
 * (TKPrimitive::moveBefore/moveAfter/moveTo), which lands in TKGroup::arrange().
 */
abstract class TKRow extends TKGroup implements PrimitiveContract
{
    use LinesUpChildren;
}
