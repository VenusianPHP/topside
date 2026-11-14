<?php

namespace Surface\Contracts\Rendering\Painting;

use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\Region;

/**
 * What a painter draws on, and what it draws from as an image: only what recording and frame
 * comparison need to know. Every Rendering framebuffer is one; an engine whose pixels are not
 * a Rendering framebuffer (a GPU texture) is one too.
 */
interface PaintTarget
{
    public function viewportWidth(): int;

    public function viewportHeight(): int;

    /** The grid damage is reported on. */
    public function damageGranularity(): DamageGranularity;

    /** True when it still holds the previous frame after the next is shown. */
    public function preservesContentsOnPresent(): bool;

    /**
     * Move the pixels of $region by ($dx, $dy), as Framebuffer::scroll() describes. One that
     * cannot keep its frame throws.
     */
    public function scroll(Region $region, int $dx, int $dy): void;
}
