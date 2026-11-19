<?php

namespace Surface\Contracts\Rendering\Enums;

enum AlphaModel: string
{
    case STRAIGHT = 'straight';
    case PREMULTIPLIED = 'premultiplied';
}