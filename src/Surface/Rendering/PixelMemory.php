<?php

namespace Surface\Rendering;

use Surface\Contracts\Rendering\Enums\AlphaModel;
use Surface\Contracts\Rendering\Enums\ChannelOrder;
use Surface\Contracts\Rendering\PixelMemoryException;
use Surface\Contracts\Rendering\PixelMemory as MemoryContract;

final class PixelMemory implements MemoryContract
{
    /**
     * Frames are 32-bit by design: every drawer draws four-byte pixels. A target that takes another format converts
     * on the way out, in its own native code.
     */
    public const int BYTES_PER_PIXEL = 4;

    public private(set) int $version = 0;

    /**
     * @param int $address - the address of the first byte of the top row
     * @param int $width - pixels per row
     * @param int $height - rows
     * @param int $stride - bytes from the start of one row to the start of the next
     * @param ChannelOrder $order
     * @param AlphaModel $alpha
     * @param object $owner - whatever allocated the memory; holding it keeps the memory alive
     */
    public function __construct(
        public readonly int $address,
        public readonly int $width,
        public readonly int $height,
        public readonly int $stride,
        public readonly ChannelOrder $order,
        public readonly AlphaModel $alpha,
        public readonly object $owner,

    ) {
        if ($address === 0) {
            throw PixelMemoryException::memoryAlreadyHasAnAddress();
        }
        if ($width < 1 || $height < 1) {
            throw PixelMemoryException::invalidSize($width, $height);
        }
        if ($stride < $width * self::BYTES_PER_PIXEL) {
            throw PixelMemoryException::wrongStrideWidth($width, $stride);
        }
    }

    /**
     * The whole frame as a rectangle.
     *
     * @return Region
     */
    public function bounds(): Region
    {
        return new Region(0, 0, $this->width, $this->height);
    }

    /**
     * The address of the first byte of the pixel at ($x, $y).
     *
     * @param int $x
     * @param int $y
     * @return int
     */
    public function at(int $x, int $y): int
    {
        return $this->address + $y * $this->stride + $x * self::BYTES_PER_PIXEL;
    }

    public function wrote(): void
    {
        $this->version++;
    }

}