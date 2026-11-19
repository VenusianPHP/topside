<?php

namespace Surface\Contracts\Rendering;

interface Frame
{
    public function memory(): PixelMemory;

    /**
     * Says these regions of the memory were written. Adds one to the memory's version.
     */
    public function wrote(Region ...$regions): void;

}