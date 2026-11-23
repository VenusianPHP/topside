<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\UI\Concerns\LinesUpChildren;
use Surface\Contracts\Toolkits\UI\TKColumn as PrimitiveContract;

/**
 * Children one under another, in display order. A child reorders itself
 * (TKPrimitive::moveBefore/moveAfter/moveTo), which lands in TKGroup::arrange().
 */
abstract class TKColumn extends TKGroup implements PrimitiveContract
{
    use LinesUpChildren;
}
