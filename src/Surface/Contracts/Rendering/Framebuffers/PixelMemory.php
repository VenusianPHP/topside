<?php

namespace Surface\Contracts\Rendering\Framebuffers;

/**
 * Where a pixel store's bytes are in memory, for native code that paints into them in place. It
 * names no library: a rasterizer that knows nothing of the store can still draw into it.
 *
 * The bytes are what PixelStore::bytes() answers: $height rows of $stride bytes each, in $format
 * (its scan direction included), colours straight, not premultiplied. They stay valid while
 * $owner lives, so whoever paints keeps $owner for as long as it holds the address.
 */
final readonly class PixelMemory
{
    /**
     * @param int $address the first byte; never 0
     * @param int $stride bytes from one row to the next, padding included
     * @param object $owner what the memory belongs to
     */
    public function __construct(
        public int $address,
        public int $width,
        public int $height,
        public int $stride,
        public FormatSpec $format,
        public object $owner,
    ) {}
}
