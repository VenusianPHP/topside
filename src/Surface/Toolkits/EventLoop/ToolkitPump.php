<?php

namespace Surface\Toolkits\EventLoop;

use Voyager\IOPools\Resources\Sleeper;
use Surface\Contracts\Toolkits\ToolkitSession;

class ToolkitPump extends Sleeper
{
    public function __construct(
        protected readonly ToolkitSession $session
    ) {}

    public function sleep(int $budget_ns): void
    {
        $this->session->pump($budget_ns);
        $this->session->flushLatest();
    }

    public function tick(): void
    {
        $this->session->pump(0);
        $this->session->flushLatest();
    }
}