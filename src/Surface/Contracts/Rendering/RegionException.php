<?php

namespace Surface\Contracts\Rendering;

use InvalidArgumentException;

class RegionException extends InvalidArgumentException
{
    public function __construct(int $width, int $height)
    {
        parent::__construct("A region is at least 1x1, got {$width}x{$height}.");
    }
}