<?php

namespace Surface\VelvetGE\Drivers\Rasterization;

use RasterScanner;
use Surface\VelvetGE\Rasterize\CLandRasterizer;
use Surface\Contracts\Rendering\Rasterization\RasterizeException;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Rendering\Rasterization\RasterizationDriver;

class CLandRasterizationDriver extends RasterizationDriver
{
    /** @throws RasterizeException When ext-rasterize is not loaded in this PHP. */
    public function __construct()
    {
        if (! class_exists(RasterScanner::class)) {
            throw RasterizeException::extensionMissing();
        }
    }

    public function driver(): string
    {
        return 'ext';
    }

    public function rasterizer(Region $clip, Edges $edges): CLandRasterizer
    {
        return new CLandRasterizer($clip, $edges);
    }
}