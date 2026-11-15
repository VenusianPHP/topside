<?php

namespace Surface\HumanInput;

use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\LoopResources\Background;
use Voyager\IOPools\Resources\Pollable;

/**
 * Human input on the loop: one poll a turn. Background, so input never keeps a loop running. The
 * toolkit keeps its own pump: a backend that rides it collects during the pump, and the poll
 * applies what it collected.
 */
final class HumanInputPoller extends Pollable implements Background
{
    public const string NAME = 'human-input';

    public function __construct(private readonly HumanInputManager $input) {}

    public function tick(): void
    {
        $this->input->poll();
    }

    public static function join(Loop $loop, HumanInputManager $input): void
    {
        $loop->resource(self::NAME, new self($input));
        $loop->onStop(fn () => $input->destroy());
    }
}
