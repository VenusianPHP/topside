<?php

namespace Surface\Contracts\Rendering\Rasterization;

use Surface\Contracts\Rendering\Framebuffers\Region;

/** Where the geometry runs. Mints rasterizers; callers never name a scanner class. */
interface RasterizeDriver
{
    /** The name it is registered under: 'native' (PHP), 'ext' (ext-rasterize), or a library binding's own. */
    public function driver(): string;

    /** @throws RasterizeException When the clip is empty or not inside 0..65535. */
    public function rasterizer(Region $clip, Edges $edges): Rasterizer;
}
