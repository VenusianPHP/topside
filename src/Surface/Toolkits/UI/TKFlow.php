<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\UI\Concerns\LinesUpChildren;
use Surface\Contracts\Toolkits\UI\TKFlow as PrimitiveContract;

/**
 * Children side by side, wrapping onto the next line where the width runs out. A child
 * reorders itself (TKPrimitive::moveBefore/moveAfter/moveTo), which lands in TKGroup::arrange().
 * Each toolkit wraps with its own flow layout, or places the children itself where it has none.
 */
abstract class TKFlow extends TKGroup implements PrimitiveContract
{
    use LinesUpChildren;
}
