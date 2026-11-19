<?php

namespace Surface\Rendering;

use Surface\Contracts\Rendering\Frame as FrameContract;
use Surface\Contracts\Rendering\PixelMemory as MemoryContract;
use Surface\Contracts\Rendering\Region as RegionContract;

final class Frame implements FrameContract
{
    public function __construct(
        private readonly MemoryContract $memory,
    ) {}

    public function memory(): MemoryContract
    {
        return $this->memory;
    }

    public function wrote(RegionContract ...$regions): void
    {
        if ($regions !== []) {
            $this->memory->wrote();
        }
    }
}