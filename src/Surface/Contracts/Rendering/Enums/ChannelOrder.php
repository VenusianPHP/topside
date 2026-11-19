<?php

namespace Surface\Contracts\Rendering\Enums;

/**
 * Which byte of a pixel is which color.
 *  'rgba' - bytes in memory: red, green, blue, alpha
 *  'bgra' - bytes in memory: blue, green, red, alpha
 */
enum ChannelOrder: string
{
    case RGBA = 'rgba';   // bytes in memory: red, green, blue, alpha
    case BGRA = 'bgra';   // bytes in memory: blue, green, red, alpha
}