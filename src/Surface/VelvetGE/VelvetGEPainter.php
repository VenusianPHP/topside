<?php

namespace Surface\VelvetGE;

use Surface\Contracts\Rendering\Painting\PaintTarget;
use Surface\Graphics\GEFramebuffer;
use Surface\Graphics\GEPainter;

/** Velvet's painter: Rendering's, from whichever painting driver the application has. */
class VelvetGEPainter extends GEPainter
{
    protected function source(GEFramebuffer $framebuffer): PaintTarget
    {
        /** @var VelvetGEFramebuffer $framebuffer the base checked it is this engine's */
        return $framebuffer->inner();
    }
}
