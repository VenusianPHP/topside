<?php

namespace Surface\Contracts\Rendering;

use InvalidArgumentException;

class DrawerException extends InvalidArgumentException
{
    public static function doesNotDrawInto(string $drawer, PixelMemory $memory): static
    {
        return new static("Drawer [{$drawer}] does not draw into {$memory->order->value}, {$memory->alpha->value} memory.");
    }

    public static function extensionMissing(string $drawer, string $extension): static
    {
        return new static("Drawer [{$drawer}] needs ext-{$extension}, which is not loaded.");
    }

    /** The library behind the drawer refused a step: $what names it. */
    public static function libraryRefused(string $drawer, string $what): static
    {
        return new static("Drawer [{$drawer}]: the library refused {$what}.");
    }

    public static function noneConfigured(): static
    {
        return new static('No drawer is configured: set rendering.drawers.default (RENDERING_DRAWERS) to a registered name.');
    }
}
