<?php

declare(strict_types=1);

namespace Venusian\Topside\Tests\Support\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\FramebufferDriver;

/**
 * How far a driver's blended pixel bytes may be from VelvetGE's. VelvetGE's drivers are the
 * reference and have none. A library package that blends with its own arithmetic sets its
 * own, by driver name, where it adds its driver to the datasets:
 *
 *     Tolerance::$by['pixman'] = 2;
 */
final class Tolerance
{
    /** @var array<string, int> driver name => greatest distance per byte */
    public static array $by = [];

    public static function of(FramebufferDriver $driver): int
    {
        return self::$by[$driver->driver()] ?? 0;
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
