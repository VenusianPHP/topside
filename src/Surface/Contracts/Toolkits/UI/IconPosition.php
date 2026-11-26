<?php

namespace Surface\Contracts\Toolkits\UI;

/**
 * Which side of a button's label its icon sits on, in the reading direction.
 */
enum IconPosition: string
{
    case LEADING = 'leading';
    case TRAILING = 'trailing';
}
