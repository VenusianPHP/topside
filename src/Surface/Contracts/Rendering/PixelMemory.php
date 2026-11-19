<?php

namespace Surface\Contracts\Rendering;

use Surface\Contracts\Rendering\Enums\AlphaModel;
use Surface\Contracts\Rendering\Enums\ChannelOrder;

interface PixelMemory
{
    public int $address { get; }

    public int $width { get; }

    public int $height { get; }

    public int $stride { get; }

    public ChannelOrder $order { get; }

    public AlphaModel $alpha { get; }

    public object $owner { get; }

    public function bounds(): Region;

    public function at(int $x, int $y): int;

    /**
     * How many times wrote() was called: a different number means the pixels changed.
     */
    public int $version { get; }

    /**
     * Says the pixels changed. Adds one to $version.
     */
    public function wrote(): void;

}