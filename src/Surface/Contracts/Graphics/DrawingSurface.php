<?php

namespace Surface\Contracts\Graphics;

use Closure;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\Region;

interface DrawingSurface
{
    public function engine(): GraphicsEngine;

    public function width(): int;

    public function height(): int;

    public function format(): FormatSpec;

    public function render(Closure $draw): static;  // one frame; $draw receives the painter

    /**
     * Between frames, moves the pixels of $region by ($dx, $dy): what moves past the region's edge
     * is dropped, and the strip left behind keeps its old pixels until the next render() draws it.
     * The next frame redraws the region, so what it draws there lands right. A surface that draws
     * into a back buffer (a ring) moves the back one: the move shows with the next frame. For an
     * engine whose has(Feature::SCROLLING) is true.
     *
     * @throws DrawingException When the engine cannot scroll this surface, or a frame is open.
     */
    public function scroll(Region $region, int $dx, int $dy): static;

    public function present(): void;                // hand the finished frame to the stage

    public function read(): string;                 // RGBA8 bytes, for capture and tests

    public function release(): void;
}