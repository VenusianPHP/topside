<?php

namespace Surface\Contracts\Images;

use Surface\Contracts\Rendering\deprecated\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\deprecated\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Region;

/**
 * Writes one frame into a framebuffer whose pixels live in PHP, through that store's own calls.
 * Only Lustrous PHP implements this, and only its store accepts it. A framebuffer that lends a
 * PixelMemory is written by ImageDecoder instead, at the address, so the pixels never become a
 * PHP string on the way in.
 */
interface StoreImageDecoder
{
    /** The name it is registered under. */
    public function driver(): string;

    /** @return list<ImageFormat> */
    public function formats(): array;

    /** Whether decodeIntoStore() can write a frame of this layout through the store's own calls. */
    public function writes(FormatSpec $format): bool;

    /**
     * The header, and nothing past it.
     *
     * @throws ImageException When the source is not a format this decoder reads, is corrupt, or is past the limit.
     */
    public function info(ImageSource $source): ImageInfo;

    /**
     * Frame $frame (0 is the first) written through $target's own calls.
     *
     * @throws ImageException When the source is corrupt, past the limit, $frame is not in the file,
     *                        or $target lends a PixelMemory.
     */
    public function decodeIntoStore(ImageSource $source, InstanceThatManagesAFramebuffer $target, int $frame = 0): Region;
}
