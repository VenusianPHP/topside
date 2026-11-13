<?php

namespace Surface\VelvetGE\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\CompletePixelStore;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\PixelStoreMinter;
use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;
use Surface\VelvetGE\Framebuffers\CLand\CLandPixelStore;
use Surface\VelvetGE\Framebuffers\PHPLand\PHPLandPixelStore;

/**
 * VelvetGE's own store for a format: what runs a tier another store leaves out. Both answer the
 * same bytes, so the choice is speed only: the C store for RGBA8, where pixels move in and out
 * of it in one call each way; the PHP store for every other layout, which ext-fb can only be
 * handed word by word.
 */
final class VelvetStores implements PixelStoreMinter
{
    public function mint(FormatSpec $format, int $width, int $height): CompletePixelStore
    {
        return $format->equals(FormatSpec::rgba8()) && CLandFramebufferDriver::available()
            ? new CLandPixelStore($format, $width, $height)
            : new PHPLandPixelStore($format, $width, $height);
    }
}
