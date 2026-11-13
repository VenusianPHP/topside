<?php

namespace Surface\VelvetGE\Rasterize;

use Surface\Rendering\Rasterization\Rasterizer;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Framebuffers\Region;

/** Scanned in C. A batch is also painted in C, straight into the target's FbBuffer, when ext-fb is loaded and the target has one. */
class CLandRasterizer extends Rasterizer
{
    use PaintsDirect;

    public function driver(): string
    {
        return 'ext';
    }

    protected function mint(Region $clip, Edges $edges): CLandScanner
    {
        return new CLandScanner($clip, $edges);
    }
}
