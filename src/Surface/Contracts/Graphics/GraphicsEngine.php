<?php

namespace Surface\Contracts\Graphics;

/**
 * What runs the rendering pipeline and presents to a stage. How it gets to pixels is its own: Graphics asks only for
 * a surface to draw on and for that surface to be shown.
 */
interface GraphicsEngine
{
    /** The name it is registered under: 'velvet', 'blend2d', 'thorvg', ... */
    public function name(): string;

    /** Whether it draws on the GPU. */
    public function gpu(): bool;

    /** A surface that draws for this stage and presents to it, at the stage's size each frame. */
    public function surface(ActorStage $stage): DrawingSurface;

    /** A surface of its own, shown nowhere. */
    public function offscreen(int $width, int $height): DrawingSurface;

    /** Lets go of everything it made. */
    public function release(): void;
}
