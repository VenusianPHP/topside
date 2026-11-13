<?php

namespace Surface\Contracts\Rendering\Framebuffers;

/**
 * Every tier: what a framebuffer kind draws through. VelvetGE's two stores are complete
 * themselves; any other store is made complete when it is minted into a kind.
 */
interface CompletePixelStore extends PaintingPixelStore, EffectsPixelStore
{

}
