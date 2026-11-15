<?php

namespace Surface\Rendering\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\InstanceThatManagesAFramebuffer;

/**
 * What a framebuffers manager hands out: one module's way of making framebuffers. Every driver
 * extends it. A driver that cannot run in this PHP (its extension is not loaded, or is too old)
 * throws a FramebufferException from its constructor, so 'auto' can take the next one.
 */
abstract class FramebufferDriver
{
    /** The name the driver is registered under. */
    abstract public function driver(): string;

    /** A blank framebuffer of $width x $height pixels in $format, the kind this driver makes. */
    abstract public function make(FormatSpec $format, int $width, int $height): InstanceThatManagesAFramebuffer;
}
