<?php

namespace Surface\Toolkits\Views;

/**
 * Where a column's or row's children gather along its axis when it has room to spare.
 */
enum Justify: string
{
    case START = 'start';
    case CENTER = 'center';
    case END = 'end';
}
