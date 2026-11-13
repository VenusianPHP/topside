<?php

namespace Surface\Toolkits\Support;

use Closure;

/**
 * A value moved over time on the event loop, a frame at a time: what a toolkit primitive has no
 * transition for (a move inside a fixed), or one that must look the same on every toolkit.
 */
final class Tween
{
    /** @var array<string, true> the keys with a timer on the loop */
    private static array $running = [];

    /**
     * Call $step with 0.0..1.0 (eased out) about 60 times a second for $seconds, the last call with
     * exactly 1.0, then $done. A tween started under a key that is still running replaces it: its
     * $done is not called.
     *
     * @param Closure(float): void $step
     */
    public static function run(string $key, float $seconds, Closure $step, ?Closure $done = null): void
    {
        self::stop($key);

        $started = microtime(true);
        self::$running[$key] = true;

        app('event-loop')->every(1 / 60, function () use ($key, $seconds, $step, $done, $started): void {
            $t = $seconds <= 0.0 ? 1.0 : min(1.0, (microtime(true) - $started) / $seconds);
            $step(1.0 - (1.0 - $t) ** 3);

            if ($t >= 1.0) {
                self::stop($key);
                $done?->__invoke();
            }
        }, "tween.{$key}");
    }

    /**
     * The value $t of the way from $from to $to: exactly $to at the end, and never outside the two.
     * Plain arithmetic can land a hair past the end (0.42 + 0.58 is not always 1.0), and a toolkit
     * refuses an opacity above 1.
     */
    public static function between(float $from, float $to, float $t): float
    {
        if ($t >= 1.0) {
            return $to;
        }

        return max(min($from, $to), min(max($from, $to), $from + ($to - $from) * $t));
    }

    public static function stop(string $key): void
    {
        if (isset(self::$running[$key])) {
            unset(self::$running[$key]);
            app('event-loop')->forget("tween.{$key}");
        }
    }
}
