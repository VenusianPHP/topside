<?php

namespace Surface\Contracts\Rendering\Rasterization;

use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\RenderingException;

class RasterizeException extends RenderingException
{
    public static function notFinite(string $what): self
    {
        return new self("{$what} is not a finite number.");
    }

    public static function pastLimit(string $what, float $value, float $limit): self
    {
        return new self("{$what} {$value} is past ±{$limit}.");
    }

    public static function notAPoint(int $position): self
    {
        return new self("Point {$position} is not [x, y].");
    }

    public static function clip(Region $clip): self
    {
        return new self("The clip {$clip->width}x{$clip->height} at ({$clip->x}, {$clip->y}) is empty or not inside 0..65535.");
    }

    /** A driver whose extension is not loaded. The 'auto' driver takes this as "try the next one". */
    public static function extensionMissing(string $driver = 'ext', string $extension = 'ext-rasterize 0.10 or newer'): self
    {
        return new self("The '{$driver}' rasterization driver needs {$extension} loaded in this PHP (".PHP_BINARY.').');
    }
}
