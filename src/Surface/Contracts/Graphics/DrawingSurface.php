<?php

namespace Surface\Contracts\Graphics;

use Closure;

/**
 * Where an engine draws: one surface, one stage (or none, offscreen).
 */
interface DrawingSurface
{
    /** @return array{int, int} width and height in pixels: the stage's now, or the offscreen size */
    public function size(): array;

    /**
     * Draws one frame: $frame is given the Canvas to draw it with.
     *
     * @param Closure(Canvas): void $frame
     */
    public function draw(Closure $frame): static;

    /**
     * Shows what was drawn since the last present on the stage.
     *
     * @throws GraphicsException for an offscreen surface: it has no stage
     */
    public function present(): static;
}
