<?php

namespace Surface\Contracts\Typography;

/** Where a typeset line sits across the width of its text. */
enum TextAlign: string
{
    case LEFT = 'left';
    case CENTER = 'center';
    case RIGHT = 'right';
}
