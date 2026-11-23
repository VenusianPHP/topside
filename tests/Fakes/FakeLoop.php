<?php

namespace Venusian\Topside\Tests\Fakes;

use Closure;
use LogicException;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\LoopResource;
use Voyager\Contracts\IOPools\LoopResources\Timer;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\IOPools\Task;
use Voyager\Contracts\IOPools\WakeReason;

/**
 * An event loop that records what a session gives it. Nothing runs.
 */
class FakeLoop implements Loop
{
    /** @var list<object> */
    public array $posted = [];

    /** @var array<string, LoopResource> */
    public array $resources = [];

    public ?string $crowned = null;

    public function __construct(
        public ?int $fd = 7,
    ) {}

    public function post(object $mail): void
    {
        $this->posted[] = $mail;
    }

    public function resource(string $name, LoopResource $resource): LoopResource
    {
        return $this->resources[$name] = $resource;
    }

    public function crown(string $name): void
    {
        $this->crowned = $name;
    }

    public function descriptor(): ?int
    {
        return $this->fd;
    }

    public function forget(string $name): void
    {
        unset($this->resources[$name]);
    }

    public function at(float $delay_s, callable $fire): Timer { throw new LogicException(__METHOD__); }

    public function every(float $interval_s, callable $fire, string $name): Timer { throw new LogicException(__METHOD__); }

    public function promise(): Promise { throw new LogicException(__METHOD__); }

    public function await(mixed $value): mixed { throw new LogicException(__METHOD__); }

    public function async(callable $body): Task { throw new LogicException(__METHOD__); }

    public function defer(Closure $work): Promise { throw new LogicException(__METHOD__); }

    public function supports(WakeReason $kind): bool { return false; }

    public function onStop(callable $hook): void {}

    public function onStopSignal(callable $handler): void {}

    public function ignoreStopSignals(): void {}

    public function run(): int { throw new LogicException(__METHOD__); }

    public function stop(int $status = 0): void {}

    public function until(Closure $assertion): void { throw new LogicException(__METHOD__); }
}
