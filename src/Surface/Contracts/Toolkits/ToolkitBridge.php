<?php

namespace Surface\Contracts\Toolkits;

interface ToolkitBridge
{
    public function connect(): ToolkitSession;

    public function session(): ?ToolkitSession;
}