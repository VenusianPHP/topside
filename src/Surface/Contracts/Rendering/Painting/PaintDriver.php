<?php

namespace Surface\Contracts\Rendering\Painting;

use Surface\Contracts\Rendering\Rasterization\Edges;
use VelvetGE\Contracts\Framebuffers\CompositingFramebuffer;
use Surface\Contracts\Rendering\Painting\PaintTarget;

interface PaintDriver
{
    /** 'native', 'ext', or a library's name. */
    public function driver(): string;

    /**
     * A painter that draws into $target.
     *
     * @param  Edges|null  $edges  Null picks by the target's format: anti-aliased where it can blend.
     */
    public function painter(CompositingFramebuffer&PaintTarget $target, ?Edges $edges = null): Painter;
}
