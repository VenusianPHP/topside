<?php

namespace Surface\Contracts\Rendering\Raycasting;

use Surface\Contracts\Rendering\Framebuffers\Framebuffer;

/** A picture stretched over each face of a wall: RGBA8 pixels, top row first. */
readonly class Texture
{
    /**
     * @param string $rgba8 width * height * 4 bytes.
     * @throws RaycastException When a side is outside 1..Grid::MAX_SIDE, or $rgba8 is another length.
     */
    public function __construct(
        public string $rgba8,
        public int $width,
        public int $height,
    ) {
        if ($width < 1 || $width > Grid::MAX_SIDE) {
            throw RaycastException::side("A texture's width", $width, Grid::MAX_SIDE);
        }
        if ($height < 1 || $height > Grid::MAX_SIDE) {
            throw RaycastException::side("A texture's height", $height, Grid::MAX_SIDE);
        }
        if (strlen($rgba8) !== $width * $height * 4) {
            throw new RaycastException("A texture {$width}x{$height} holds ".($width * $height * 4).' bytes of RGBA8; got '.strlen($rgba8).'.');
        }
    }

    /** What a framebuffer holds now, as a texture. */
    public static function of(Framebuffer $frame): self
    {
        return new self($frame->toRgba8(), $frame->viewportWidth(), $frame->viewportHeight());
    }
}
