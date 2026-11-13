<?php

namespace Surface\VelvetGE\Rasterize;

use Surface\Rendering\Rasterization\Rasterizer;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Framebuffers\Region;

class PHPLandRasterizer extends Rasterizer
{
    public function driver(): string
    {
        return 'native';
    }

    protected function mint(Region $clip, Edges $edges): PHPLandScanner
    {
        return new PHPLandScanner($clip, $edges);
    }
}