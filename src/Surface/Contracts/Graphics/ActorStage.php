<?php

namespace Surface\Contracts\Graphics;

use Surface\Contracts\Rendering\PixelMemory;
use Surface\Contracts\Rendering\Region;

/**
 * Where an engine's frames are shown: a canvas in a toolkit window, a staged window, an embedded display. It draws
 * nothing itself.
 */
interface ActorStage
{
    /** @return array{int, int} its size in pixels now: [0, 0] while it has none */
    public function pixelSize(): array;

    /** Pixels to one unit of its layout: 2.0 on a display of twice the density. */
    public function pixelScale(): float;

    /**
     * Shows these regions of the memory. The memory stays the engine's; the stage reads it where it lies.
     */
    public function present(PixelMemory $memory, Region ...$changed): void;
}
