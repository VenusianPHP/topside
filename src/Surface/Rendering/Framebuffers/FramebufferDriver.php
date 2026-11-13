<?php

namespace Surface\Rendering\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\FramebufferDriver as FramebufferDriverContract;

/**
 * What the framebuffers manager hands out. Every driver extends it: VelvetGE's two, and each
 * library binding's.
 */
abstract class FramebufferDriver implements FramebufferDriverContract
{

}
