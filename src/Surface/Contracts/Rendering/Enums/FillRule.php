<?php

namespace Surface\Contracts\Rendering\Enums;

enum FillRule: string
{
    case NON_ZERO = 'non-zero';   // a five-pointed star is filled solid
    case EVEN_ODD = 'even-odd';   // the star's center is left empty
}