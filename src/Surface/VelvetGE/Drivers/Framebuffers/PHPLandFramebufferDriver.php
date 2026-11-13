<?php

namespace Surface\VelvetGE\Drivers\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\VelvetGE\Framebuffers\PHPLand\PHPLandPixelStore;

/** The five kinds with their bytes in PHP strings. Always available: what 'auto' takes last. */
class PHPLandFramebufferDriver extends StoreFramebufferDriver
{
    public function driver(): string
    {
        return 'native';
    }

    public function mint(FormatSpec $format, int $width, int $height): PHPLandPixelStore
    {
        return new PHPLandPixelStore($format, $width, $height);
    }
}
