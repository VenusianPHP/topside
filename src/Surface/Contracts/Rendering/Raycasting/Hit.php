<?php

namespace Surface\Contracts\Rendering\Raycasting;

/** Where a ray stopped: the first wall cell it entered. */
readonly class Hit
{
    /**
     * @param float $distance From the eye to the face. For cast(), in cells along the ray. For a fan's column, in
     *                        cells along the camera's direction, not along the ray, so a flat wall has one distance.
     * @param float $along Where on the face, 0 <= along < 1, measured toward +y on a west or east face and toward +x on a north or south one.
     * @param int $x The wall's cell.
     * @param int $y
     * @param int $wall The cell's value, 1..255.
     */
    public function __construct(
        public float $distance,
        public float $along,
        public int $x,
        public int $y,
        public int $wall,
        public Face $face,
    ) {}
}
