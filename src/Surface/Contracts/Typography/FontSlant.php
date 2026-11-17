<?php

namespace Surface\Contracts\Typography;

/** The slant a font request asks for, as CSS names it. */
enum FontSlant: string
{
    case UPRIGHT = 'upright';
    case ITALIC = 'italic';
    case OBLIQUE = 'oblique';
}
