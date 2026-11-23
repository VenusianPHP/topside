<?php

namespace Surface\Contracts\Toolkits;

use Voyager\Contracts\IOPools\Loop as LoopInterface;

interface ToolkitSession
{
    public function connected(): bool;

    public function disconnect(): void;

    public function flushLatest(): void;

    public function post(object $mail): void;

    public function pump(int $budget_ns): int;

    public function connect(): ToolkitSession;

    public function joinLoop(LoopInterface $loop): void;
}