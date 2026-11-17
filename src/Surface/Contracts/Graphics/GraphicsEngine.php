<?php

namespace Surface\Contracts\Graphics;

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;

interface GraphicsEngine
{
    // Identity
    public function name(): string;                 // 'velvet', 'metal', 'vulkan', 'opengl', 'sdl3'

    public function backend(): Backend;             // CPU or GPU

    public function modules(): array;               // part => what drives it, for display

    // Capabilities
    public function has(Feature $feature): bool;    // GRADIENTS, EFFECTS, SCROLLING, RAYCASTING, ...

    // Surfaces: where pixels go
    public function surface(ActorStage $stage, ?FormatSpec $format = null): DrawingSurface;

    public function offscreen(int $width, int $height, ?FormatSpec $format = null): DrawingSurface;

    // Lifetime
    public function release(): void;
}