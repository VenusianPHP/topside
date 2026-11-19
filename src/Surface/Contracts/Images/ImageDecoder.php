<?php

namespace Surface\Contracts\Images;

use Surface\Contracts\Rendering\deprecated\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\deprecated\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Region;

/**
 * Writes one frame of a compressed image into a PixelMemory, in C. The frame's colours are
 * straight, which is what PixelMemory already promises. A decoder says which formats it reads and
 * which frame layouts it can write; it does not convert a layout it declined.
 *
 * Lustrous PHP does not implement this. Its store lends no PixelMemory, and it writes through the
 * store's own calls (StoreImageDecoder).
 */
interface ImageDecoder
{
    /** The name it is registered under. */
    public function driver(): string;

    /** @return list<ImageFormat> */
    public function formats(): array;

    /** Whether decodeInto() can write a frame of this layout. */
    public function writes(FormatSpec $format): bool;

    /**
     * The header, and nothing past it.
     *
     * @throws ImageException When the source is not a format this decoder reads, is corrupt, or is past the limit.
     */
    public function info(ImageSource $source): ImageInfo;

    /**
     * Frame $frame (0 is the first) written at $target, which this decoder has already accepted.
     * Bytes outside the returned region are left as they were.
     *
     * @throws ImageException When the source is corrupt, past the limit, or $frame is not in the file.
     */
    public function decodeInto(ImageSource $source, PixelMemory $target, int $frame = 0): Region;
}
