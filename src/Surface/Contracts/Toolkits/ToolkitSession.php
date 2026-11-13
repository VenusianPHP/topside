<?php

namespace Surface\Contracts\Toolkits;

use Voyager\Contracts\IOPools\Loop as LoopInterface;

interface ToolkitSession
{
    public function connect(): static;

    public function connected(): bool;

    public function disconnect(): void;

    public function joinLoop(LoopInterface $loop): void;
}