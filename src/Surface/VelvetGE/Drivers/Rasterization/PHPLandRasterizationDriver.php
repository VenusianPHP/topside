<?php

namespace Surface\VelvetGE\Drivers\Rasterization;

use Surface\VelvetGE\Rasterize\PHPLandRasterizer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Rendering\Rasterization\RasterizationDriver;

class PHPLandRasterizationDriver extends RasterizationDriver
{
    public function driver(): string
    {
        return 'native';
    }

    public function rasterizer(Region $clip, Edges $edges): PHPLandRasterizer
    {
        return new PHPLandRasterizer($clip, $edges);
    }
}