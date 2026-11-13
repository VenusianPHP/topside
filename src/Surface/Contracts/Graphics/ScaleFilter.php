<?php

namespace Surface\Contracts\Graphics;

/** How a framebuffer of another size is sampled onto the window. */
enum ScaleFilter: string
{
    case Linear = 'linear';
    case Nearest = 'nearest';
}
