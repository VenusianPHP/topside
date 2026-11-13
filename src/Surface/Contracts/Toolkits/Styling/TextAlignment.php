<?php

namespace Surface\Contracts\Toolkits\Styling;

/**
 * Horizontal text alignment inside a view. Windows only, never Drawing.
 */
enum TextAlignment: string
{
    case LEFT = 'left';
    case CENTER = 'center';
    case RIGHT = 'right';
}
