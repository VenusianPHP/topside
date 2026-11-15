<?php

namespace VelvetGE\Contracts\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\Filter;
use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Framebuffers\Region;

/**
 * A store that works from another frame's ticket (its PixelMemory) instead of an RGBA8 copy of
 * its pixels: the store's library reads the source where it is, and PHP never holds the bytes on
 * the way. A store answers takes() for the tickets its library can read; for any other the frame
 * falls back to an RGBA8 read-back.
 */
interface TicketPixelStore extends PixelStore
{
    /** Whether this store's library can read $source where it is, for blitMemory() and paintMemory(). */
    public function takes(PixelMemory $source): bool;

    /**
     * The rect $from of $source copied to ($x, $y), clipped to the store and to $clip, as
     * blitRgba8() clips. Answers the rect written, or null when nothing landed.
     */
    public function blitMemory(PixelMemory $source, Region $from, int $x, int $y, ?Region $clip = null): ?Region;

    /** paintRgba8() with the image read from $source. */
    public function paintMemory(PixelMemory $source, array $inverse, Region $target, int $opacity, Filter $filter, int $row = 0, int $tint = 0xFFFFFFFF, ?array $window = null, ?string $coverage = null): void;

    /** The rect $source moved by ($dx, $dy) within the store, in the store's library: the rects may overlap. */
    public function shift(Region $source, int $dx, int $dy): void;
}
