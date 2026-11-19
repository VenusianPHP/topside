<?php

namespace Surface\Toolkits\Demo;

use Closure;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\LoopResources\Timer;

/**
 * Draws a page's frames at most $rate a second. Each frame is due one interval after the last one was
 * due, not after it started: a timer fires a little late, and counting from when it fired would add
 * that lateness to every interval, so 120 a second would draw about 108. A frame that overran is
 * followed by the next straight away, and the time it lost is not made up: the next is due one interval
 * after that. The rate is the target, or what a frame's cost allows, whichever is lower.
 *
 * Not a repeating timer: a repeating timer keeps to a grid and skips the beats it is late for, so a
 * frame a little longer than its slot waits for the next slot, and 120 a second drops to 60, 40, 30
 * the moment a frame takes longer than 8.3, 16.7, 25 ms.
 *
 * A name runs one clock: run() under a name already running replaces it, stop() ends it.
 */
final class FrameClock
{
    /** @var array<string, array{object, ?Timer}> the run's token and its next frame's timer, by name */
    private static array $running = [];

    /** Draws $frame at most $rate times a second; 0 draws one every turn of the loop. On $loop, else the application's. */
    public static function run(string $name, float $rate, Closure $frame, ?Loop $loop = null): void
    {
        self::stop($name);
        $loop ??= app('event-loop');
        $token = new \stdClass;
        $interval = $rate > 0 ? 1 / $rate : 0.0;
        $due = microtime(true);
        $tick = function () use (&$tick, &$due, $name, $token, $interval, $frame, $loop): void {
            $frame();
            // stopped, or run again, while the frame drew: this run ends here
            if ((self::$running[$name][0] ?? null) !== $token) {
                return;
            }
            $now = microtime(true);
            $due = max($due + $interval, $now);
            self::$running[$name][1] = $loop->at($due - $now, $tick);
        };
        self::$running[$name] = [$token, $loop->at(0.0, $tick)];
    }

    public static function stop(string $name): void
    {
        (self::$running[$name][1] ?? null)?->cancel();
        unset(self::$running[$name]);
    }
}
