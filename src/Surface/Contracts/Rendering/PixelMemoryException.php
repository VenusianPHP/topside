<?php

namespace Surface\Contracts\Rendering;

use InvalidArgumentException;

class PixelMemoryException extends InvalidArgumentException
{
    public static function memoryAlreadyHasAnAddress(): static
    {
        return new self('Pixel memory has an address; 0 is no memory.');
    }

    public static function invalidSize(int $width, int $height): static
    {
        return new self("Pixel memory is at least 1x1, got {$width}x{$height}.");
    }

    public static function wrongStrideWidth(int $width, int $stride): static
    {
        return new self("A row of {$width} pixels is ".($width * 4)." bytes; the stride is {$stride}.");
    }
}