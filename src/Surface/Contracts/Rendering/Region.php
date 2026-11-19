<?php

namespace Surface\Contracts\Rendering;

interface Region
{
    public int $x { get; }

    public int $y { get; }

    public int $width { get; }

    public int $height { get; }

    public function right(): int;

    public function firstColumnPastTheRightEdge(): int;

    public function bottom(): int;

    public function firstRowPastTheBottomEdge(): int;

    public function intersect(Region $other): ?Region;

    public function union(Region $other): Region;
}