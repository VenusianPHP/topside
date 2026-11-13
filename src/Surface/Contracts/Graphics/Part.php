<?php

namespace Surface\Contracts\Graphics;

/** What a graphics engine hands out. An engine offers a part or it does not: GraphicsEngine::offers(). */
enum Part: string
{
    /** Where pixels live: GraphicsEngine::framebuffer(). */
    case FRAMEBUFFER = 'framebuffer';

    /** Shapes onto a framebuffer: GraphicsEngine::rasterizer(). */
    case RASTERIZER = 'rasterizer';

    /** A grid world seen from a camera: GraphicsEngine::raycaster(). */
    case RAYCASTER = 'raycaster';

    /** The drawing API (transforms, clips, layers, text): GraphicsEngine::painter(). */
    case PAINTER = 'painter';
}
