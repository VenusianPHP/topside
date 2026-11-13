<?php

namespace Surface\Graphics\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Surface\VelvetGE\Framebuffers\DirtyFramebuffer;
use Surface\VelvetGE\Framebuffers\FullFramebuffer;

/**
 * RGBA8 pixels held in PHP, for what a graphics engine keeps on the CPU side: a staging copy,
 * a shadow of a GPU target, a gradient ramp. The one place Graphics names a pixel store.
 */
final class Scratch
{
    public static function full(int $width, int $height): FullFramebuffer
    {
        return (new PHPLandFramebufferDriver())->full(FormatSpec::rgba8(), $width, $height);
    }

    /** As full(), tracking the regions written since its last epoch. */
    public static function dirty(int $width, int $height): DirtyFramebuffer
    {
        return (new PHPLandFramebufferDriver())->dirty(FormatSpec::rgba8(), $width, $height);
    }
}
