<?php

namespace VelvetGE;

use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Rendering\PixelMemory;

class VelvetGEException extends GraphicsException
{
    public static function drawerCannotDrawInto(string $drawer, PixelMemory $memory): static
    {
        return new static("Velvet's drawer [{$drawer}] does not draw into the frames Rendering's frame provider makes ({$memory->order->value}, {$memory->alpha->value}): set rendering.drawers.default and rendering.frames.default to a pair that fit.");
    }
}
