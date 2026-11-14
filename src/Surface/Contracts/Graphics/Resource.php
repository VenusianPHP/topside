<?php

namespace Surface\Contracts\Graphics;

/** What a graphics engine hands out. Each engine answers with its own version of the GE type. */
enum Resource: string
{
    /** Holds pixels: a GEFramebuffer. */
    case FRAMEBUFFER = 'framebuffer';

    /** Draws one shape into a framebuffer at once: a GERasterizer. */
    case RASTERIZER = 'rasterizer';

    /** Paints a view of a grid into a framebuffer: a GERaycaster. */
    case RAYCASTER = 'raycaster';

    /** The 2D drawing API over a framebuffer: a GEPainter. */
    case PAINTER = 'painter';
}
