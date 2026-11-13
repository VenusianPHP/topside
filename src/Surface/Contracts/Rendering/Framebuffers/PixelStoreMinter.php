<?php

namespace Surface\Contracts\Rendering\Framebuffers;

/**
 * Where a framebuffer's pixels come from: one PixelStore per surface.
 *
 * A framebuffer library needs two classes to give Topside every framebuffer kind: a PixelStore
 * that keeps the bytes in the library, and a driver that mints it. VelvetGE's kinds (full, dirty,
 * ePaper, paged, ring) take a minter and do the rest, completing a store that has the core only.
 */
interface PixelStoreMinter
{
    /**
     * A blank store in $format, $width x $height pixels.
     *
     * @throws FramebufferException When the library cannot hold $format, or the size is past its limits.
     */
    public function mint(FormatSpec $format, int $width, int $height): PixelStore;
}
