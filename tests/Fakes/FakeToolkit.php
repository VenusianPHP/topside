<?php

namespace Venusian\Topside\Tests\Fakes;

use Closure;
use Surface\Contracts\Toolkits\Toolkit;

/**
 * A toolkit library that records what the session asks of it.
 */
class FakeToolkit implements Toolkit
{
    public int $connects = 0;

    public int $disconnects = 0;

    /** @var list<int> */
    public array $pumps = [];

    /** @var list<int> */
    public array $wake_descriptors = [];

    /** What one pump reports as dispatched. */
    public int $dispatches = 3;

    /** Runs inside each pump, as a native callback would. */
    public ?Closure $during_pump = null;

    public function connect(): void
    {
        $this->connects++;
    }

    public function disconnect(): void
    {
        $this->disconnects++;
    }

    public function pump(int $budget_ns): int
    {
        $this->pumps[] = $budget_ns;
        if (! is_null($this->during_pump)) {
            ($this->during_pump)();
        }

        return $this->dispatches;
    }

    public function wakeDescriptor(int $fd): void
    {
        $this->wake_descriptors[] = $fd;
    }
}
