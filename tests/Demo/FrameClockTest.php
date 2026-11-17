<?php

declare(strict_types=1);

use Surface\Toolkits\Demo\FrameClock;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\IOPools\ResourceRegistry;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;

/** A loop of its own, run for $seconds. */
function frameClockLoop(): EventLoop
{
    $registry = new ResourceRegistry();

    return new EventLoop($registry, new LoopWaiter($registry, new StreamSelectWaiterBackend(), 5_000_000), new GuzzlePromiseEngine());
}

/** Busy for $ms milliseconds: a frame that costs that much. */
function frameCost(float $ms): void
{
    $until = hrtime(true) + (int) ($ms * 1e6);
    while (hrtime(true) < $until) {
    }
}

afterEach(fn () => FrameClock::stop('test'));

it('follows a frame that overran its slot with the next at once, not at the next slot of a grid', function (): void {
    // 13 ms frames at 100 a second: a grid timer waits for the slot after, every 20 ms (26 frames in 0.52 s);
    // the clock draws one after the other (40)
    $loop = frameClockLoop();
    $frames = 0;
    FrameClock::run('test', 100, function () use (&$frames): void {
        $frames++;
        frameCost(13);
    }, $loop);
    $loop->at(0.52, fn () => $loop->stop());
    $loop->run();

    expect($frames)->toBeGreaterThanOrEqual(36)->toBeLessThanOrEqual(41);
});

it('holds the target rate when frames cost less than their slot', function (): void {
    $loop = frameClockLoop();
    $frames = 0;
    FrameClock::run('test', 50, function () use (&$frames): void {
        $frames++;
        frameCost(2);
    }, $loop);
    $loop->at(0.5, fn () => $loop->stop());
    $loop->run();

    // one at once, then one every 20 ms
    expect($frames)->toBeGreaterThanOrEqual(24)->toBeLessThanOrEqual(26);
});

it('draws nothing more once stopped, and one clock a name', function (): void {
    $loop = frameClockLoop();
    [$first, $second] = [0, 0];
    FrameClock::run('test', 200, function () use (&$first): void {
        $first++;
    }, $loop);
    // run again under the same name: the first clock ends
    FrameClock::run('test', 200, function () use (&$second): void {
        $second++;
    }, $loop);
    $stoppedAt = null;
    $loop->at(0.1, function () use (&$second, &$stoppedAt): void {
        FrameClock::stop('test');
        $stoppedAt = $second;
    });
    $loop->at(0.2, fn () => $loop->stop());
    $loop->run();

    expect($first)->toBe(0)
        ->and($second)->toBeGreaterThan(5)
        ->and($second)->toBe($stoppedAt);
});
