<?php

namespace Surface\Rendering;

use Surface\Contracts\Rendering\RegionException;
use Surface\Contracts\Rendering\Region as RegionContract;

final readonly class Region implements RegionContract
{
    /**
     * @param int $x - left edge, in pixels from the frame's left
     * @param int $y - top edge, in pixels from the frame's top
     * @param int $width
     * @param int $height
     */
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {
        if ($width < 1 || $height < 1)
        {
            throw new RegionException($width, $height);
        }
    }

    /**
     * The first column past the right edge.
     *
     * @return int
     */
    public function right(): int
    {
        return $this->x + $this->width;
    }

    public function firstColumnPastTheRightEdge(): int
    {
        return $this->right();
    }

    /**
     * The first row past the bottom edge.
     *
     * @return int
     */
    public function bottom(): int
    {
        return $this->y + $this->height;
    }

    public function firstRowPastTheBottomEdge(): int
    {
        return $this->bottom();
    }

    /**
     * The part both rectangles cover, or null when they don't overlap.
     *
     * @param Region $other
     * @return self|null
     */
    public function intersect(RegionContract $other): ?Region
    {
        $x = max($this->x, $other->x);
        $y = max($this->y, $other->y);
        $right = min($this->right(), $other->right());
        $bottom = min($this->bottom(), $other->bottom());

        return $right > $x && $bottom > $y ? new self($x, $y, $right - $x, $bottom - $y) : null;
    }

    /** The smallest rectangle that holds both. */
    public function union(RegionContract $other): Region
    {
        $x = min($this->x, $other->x);
        $y = min($this->y, $other->y);

        return new self($x, $y, max($this->right(), $other->right()) - $x, max($this->bottom(), $other->bottom()) - $y);
    }
}