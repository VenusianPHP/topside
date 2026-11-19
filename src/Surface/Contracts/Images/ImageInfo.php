<?php

namespace Surface\Contracts\Images;

/**
 * What a decoder can say about an image from its header, before it writes a pixel: the format, the
 * size, whether a pixel can be partly transparent, and how many frames the file holds. Playback
 * timing belongs to AV. Frame numbers passed to a decoder start at 0.
 */
final readonly class ImageInfo
{
    public function __construct(
        public ImageFormat $format,
        public int $width,
        public int $height,
        public bool $hasAlpha,
        public int $frames,
    ) {}
}
