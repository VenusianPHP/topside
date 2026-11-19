<?php

namespace VelvetGE\Contracts\Framebuffers;

use Surface\Contracts\Rendering\deprecated\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Region;

/** A framebuffer that moves its own pixels. */
interface ScrollingFramebuffer extends InstanceThatManagesAFramebuffer
{
    /**
     * Move the pixels of $region, clipped to the surface, by ($dx, $dy): pixels that move past
     * the region's edge are dropped, pixels outside it do not change, and the exposed strip keeps
     * its old pixels until drawn. Damage, where tracked, is the whole clipped region. A read-only
     * framebuffer throws as for any write; a paged one, holding one page at a time, throws.
     */
    public function scroll(Region $region, int $dx, int $dy): void;
}
