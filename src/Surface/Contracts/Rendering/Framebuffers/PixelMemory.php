<?php

namespace Surface\Contracts\Rendering\Framebuffers;

/**
 * Where a frame's bytes are in C memory: the ticket one C library hands another for work on the
 * pixels (a painter drawing in, a store copying from), so PHP never holds the bytes on the way. It
 * names no library. A PHP-land frame lends none: its bytes stay opaque inside its own store, and
 * data crossing from C into PHP-land does so as an explicit read-back.
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
