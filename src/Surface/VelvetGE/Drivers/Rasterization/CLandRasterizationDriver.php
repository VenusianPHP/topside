<?php

namespace Surface\VelvetGE\Drivers\Rasterization;

use RasterScanner;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\RasterizeException;
use Surface\Contracts\Rendering\Rasterization\Scanner;
use Surface\VelvetGE\Rasterize\CLandScanner;

/** The scanning in C, through ext-rasterize. */
class CLandRasterizationDriver extends ScannerRasterizationDriver
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

    protected function scanner(Region $clip, Edges $edges): Scanner
    {
        return new CLandScanner($clip, $edges);
    }
}
