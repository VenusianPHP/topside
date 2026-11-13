<?php

namespace Surface\VelvetGE\Drivers\Painting;

use Surface\Contracts\Rendering\Painting\Brush;

/** VelvetGE's painter with no brush: every command carried out in PHP over Rendering's rasterizer and framebuffer. Always available: what 'auto' takes last. */
class PHPLandPaintingDriver extends BrushPaintingDriver
{
    public function driver(): string
    {
        return 'native';
    }

    public function brush(): ?Brush
    {
        return null;
    }
}
