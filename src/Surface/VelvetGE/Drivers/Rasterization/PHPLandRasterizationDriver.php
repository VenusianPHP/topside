<?php

namespace Surface\VelvetGE\Drivers\Rasterization;

use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\Scanner;
use Surface\VelvetGE\Rasterize\PHPLandScanner;

/** The scanning in PHP: runs anywhere. */
class PHPLandRasterizationDriver extends ScannerRasterizationDriver
{
    public function driver(): string
    {
        return 'native';
    }

    protected function scanner(Region $clip, Edges $edges): Scanner
    {
        return new PHPLandScanner($clip, $edges);
    }
}
