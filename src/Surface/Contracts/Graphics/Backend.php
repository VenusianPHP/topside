<?php

namespace Surface\Contracts\Graphics;

/** What computes an engine's pixels. An engine is made for one and keeps it. */
enum Backend: string
{
    case CPU = 'cpu';
    case GPU = 'gpu';
}
