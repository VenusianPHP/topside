<?php

namespace Surface\Contracts\Toolkits;

interface Toolkit
{
    public function connect(): void;

    public function disconnect(): void;

    public function pump(int $budget_ns): int;

    public function wakeDescriptor(int $fd): void;
}
