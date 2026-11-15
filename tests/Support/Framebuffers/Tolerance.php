<?php

declare(strict_types=1);

namespace Venusian\Topside\Tests\Support\Framebuffers;

use Surface\Rendering\Framebuffers\FramebufferDriver;

/**
 * How far a driver's blended pixel bytes may be from VelvetGE's. VelvetGE's drivers are the
 * reference and have none. A library package that blends with its own arithmetic sets its
 * own, by driver name, where it adds its driver to the datasets:
 *
 *     Tolerance::$by['pixman'] = 2;
 *
 * A library rasterization driver that blends in place with its own arithmetic (a DirectScanner
 * that is not VelvetGE's) sets its own the same way, under its own name; the DirectDraw matrix
 * allows the greater of the store's and the rasterizer's.
 */
final class Tolerance
{
    /** @var array<string, int> driver name => greatest distance per byte */
    public static array $by = [];

    public static function of(FramebufferDriver $driver): int
    {
        return self::$by[$driver->driver()] ?? 0;
    }

    /** The tolerance set under a driver's name, whichever part it is a driver of; 0 when none is. */
    public static function named(string $driver): int
    {
        return self::$by[$driver] ?? 0;
    }

    /** Whether two 0xRRGGBBAA words are within the driver's tolerance, channel by channel. */
    public static function near(FramebufferDriver $driver, int $got, int $want): bool
    {
        $tolerance = self::of($driver);
        for ($shift = 0; $shift < 32; $shift += 8) {
            if (abs((($got >> $shift) & 0xFF) - (($want >> $shift) & 0xFF)) > $tolerance) {
                return false;
            }
        }

        return true;
    }
}
