<?php

namespace Surface\Rendering;

use Surface\Contracts\Rendering\Drawer as DrawerContract;
use Surface\Rendering\Concerns\ReadsBackendConfig;

/**
 * A library's drawer, holding its settings: the config given, shaped like the one stored at $location.
 */
abstract class Drawer implements DrawerContract
{
    use ReadsBackendConfig;

    protected array $config;

    /**
     * @param string $location where its stored config lives, as config() reads it
     */
    public function __construct(array $config, string $location)
    {
        $this->config = $this->backendConfig($config, $location);
    }

    /** Lowered shapes as lists, unless the library's own call reads a packed struct array. */
    public function packing(string $form): ?array
    {
        return null;
    }

    /**
     * Whether a command's paint is a gradient as the recorder records one: ['linear', [x0, y0, x1, y1], stops, spread,
     * matrix] or ['radial', [cx, cy, r], stops, spread, matrix]; stops [[offset, 0xRRGGBBAA], ...], at least one, offsets
     * 0..1 and not falling;
     * spread 'pad', 'repeat' or 'reflect'; matrix [a, b, c, d, e, f], the transform the shape was drawn through.
     * Anything else is neither a colour nor a gradient a drawer can draw.
     */
    protected static function isGradient(mixed $paint): bool
    {
        if (! is_array($paint) || count($paint) !== 5 || ! array_is_list($paint)) {
            return false;
        }
        [$kind, $geometry, $stops, $spread, $matrix] = $paint;
        $size = match ($kind) { 'linear' => 4, 'radial' => 3, default => 0 };
        if ($size === 0 || ! is_array($geometry) || count($geometry) !== $size || ! array_is_list($geometry)
            || ! in_array($spread, ['pad', 'repeat', 'reflect'], true)
            || ! is_array($matrix) || count($matrix) !== 6 || ! array_is_list($matrix)
            || ! is_array($stops) || $stops === [] || ! array_is_list($stops)) {
            return false;
        }
        foreach ([...$geometry, ...$matrix] as $number) {
            if (! (is_int($number) || is_float($number))) {
                return false;
            }
        }
        $previous = 0.0;
        foreach ($stops as $stop) {
            if (! is_array($stop) || count($stop) !== 2 || ! (is_int($stop[0] ?? null) || is_float($stop[0] ?? null)) || ! is_int($stop[1] ?? null)) {
                return false;
            }
            // offsets 0..1 and not falling; colours 0xRRGGBBAA
            if (! ($stop[0] >= $previous && $stop[0] <= 1.0) || $stop[1] < 0 || $stop[1] > 0xFFFFFFFF) {
                return false;
            }
            $previous = (float) $stop[0];
        }

        return true;
    }

    /** Whether a command's paint is an int colour or a gradient. */
    protected static function isPaint(mixed $paint): bool
    {
        return is_int($paint) || self::isGradient($paint);
    }
}
