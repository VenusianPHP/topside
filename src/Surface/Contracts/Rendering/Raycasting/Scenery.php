<?php

namespace Surface\Contracts\Rendering\Raycasting;

/** How a view looks: what each wall is painted with, and the ceiling and floor behind them. */
readonly class Scenery
{
    /**
     * @param array<int, int|Texture> $walls By wall id 1..255: one colour 0xRRGGBBAA, or a texture.
     * @param int $ceiling 0xRRGGBBAA, above the middle row of a view.
     * @param int $floor 0xRRGGBBAA, from the middle row down.
     * @param int $shade 0..255: the red, green and blue of a north or south face are multiplied by $shade / 255. 255 leaves every face as it is.
     * @throws RaycastException When a wall's key is outside 1..255, a wall is neither a colour nor a texture, or a colour or the shade is out of range.
     */
    public function __construct(
        public array $walls,
        public int $ceiling = 0x000000FF,
        public int $floor = 0x000000FF,
        public int $shade = 255,
    ) {
        foreach ($walls as $id => $wall) {
            if (! is_int($id) || $id < 1 || $id > 255) {
                throw new RaycastException('A scenery\'s walls are keyed by wall id, 1..255.');
            }
            if (is_int($wall) ? ($wall < 0 || $wall > 0xFFFFFFFF) : ! $wall instanceof Texture) {
                throw new RaycastException("Wall {$id} of a scenery is a colour 0xRRGGBBAA or a Texture.");
            }
        }
        if ($ceiling < 0 || $ceiling > 0xFFFFFFFF) {
            throw RaycastException::colour("A scenery's ceiling");
        }
        if ($floor < 0 || $floor > 0xFFFFFFFF) {
            throw RaycastException::colour("A scenery's floor");
        }
        if ($shade < 0 || $shade > 255) {
            throw new RaycastException("A scenery's shade is 0..255, got {$shade}.");
        }
    }

    /**
     * The walls as plain values, for a driver: a colour as it is, a texture as [RGBA8 bytes, width, height].
     *
     * @return array<int, int|array{string, int, int}>
     */
    public function table(): array
    {
        return array_map(fn (int|Texture $wall): int|array => is_int($wall) ? $wall : [$wall->rgba8, $wall->width, $wall->height], $this->walls);
    }
}
