<?php

namespace Surface\Contracts\Rendering;

interface FrameProvider
{
    public function name(): string;

    public function frame(int $width, int $height): Frame;
}