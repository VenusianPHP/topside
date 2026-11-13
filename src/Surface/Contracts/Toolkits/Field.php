<?php

namespace Surface\Contracts\Toolkits;

use Closure;

/** What ViewBuilder::input() gives back: the way to read and empty what the user typed, whichever builder made the input. */
readonly class Field
{
    /**
     * @param Closure(): string $read
     * @param Closure(): void   $clear
     */
    public function __construct(private Closure $read, private Closure $clear) {}

    public function value(): string
    {
        return ($this->read)();
    }

    public function clear(): void
    {
        ($this->clear)();
    }
}