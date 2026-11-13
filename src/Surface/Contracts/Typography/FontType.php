<?php

namespace Surface\Contracts\Typography;

/** Bitmap faces draw on the CPU; TrueType faces draw on every engine. */
enum FontType
{
    case Bitmap;
    case TrueType;
}
