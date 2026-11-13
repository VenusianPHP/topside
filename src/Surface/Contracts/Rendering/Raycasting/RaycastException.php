<?php

namespace Surface\Contracts\Rendering\Raycasting;

use Surface\Contracts\Rendering\RenderingException;

class RaycastException extends RenderingException
{
    public static function notFinite(string $what): self
    {
        return new self("{$what} is not a finite number.");
    }

    public static function pastLimit(string $what, float $value): self
    {
        return new self("{$what} ".self::number($value).' is past ±'.Raycaster::LIMIT.'.');
    }

    public static function reach(float $reach): self
    {
        return new self('A reach is above 0, or INF; got '.self::number($reach).'.');
    }

    public static function fieldOfView(float $fov): self
    {
        return new self("A camera's field of view is above 0 and below pi radians, got ".self::number($fov).'.');
    }

    public static function side(string $what, int $value, int $most): self
    {
        return new self("{$what} is 1..{$most}, got {$value}.");
    }

    public static function colour(string $what): self
    {
        return new self("{$what} is a colour 0xRRGGBBAA.");
    }

    public static function noWall(int $wall): self
    {
        return new self("Wall {$wall} has no colour or texture in the scenery.");
    }

    public static function outside(int $x, int $y, int $width, int $height, int $surfaceWidth, int $surfaceHeight): self
    {
        return new self("A view of {$width}x{$height} at ({$x}, {$y}) is not inside the {$surfaceWidth}x{$surfaceHeight} framebuffer.");
    }

    public static function disabled(): self
    {
        return new self("Raycasting is off. Turn it on with 'enabled' => true under 'raycaster' in config/rendering.php, or RENDERING_RAYCASTER=true in the environment.");
    }

    public static function extensionMissing(string $version): self
    {
        return new self("The 'ext' raycasting driver needs ext-raycast {$version} or newer loaded in this PHP (".PHP_BINARY.').');
    }

    /** A number as text: NAN by name, since PHP 8.5 warns when it is turned into a string. */
    private static function number(float $value): string
    {
        return is_nan($value) ? 'NAN' : (string) $value;
    }
}
