<?php

namespace Surface\Contracts\Rendering\Raycasting;

/**
 * An eye in a Grid: where it stands, which way it looks, how wide it sees.
 * Angles are radians; 0 looks east and the angle grows clockwise as the grid
 * is drawn, toward south.
 */
readonly class Camera
{
    /** 60 degrees. */
    public const float FOV = M_PI / 3;

    /**
     * @param float $x Where it stands, in cells.
     * @param float $y
     * @param float $angle Which way it looks.
     * @param float $fov How wide it sees, edge to edge: above 0 and below a half turn.
     * @throws RaycastException When a number is not finite, the position is past Raycaster::LIMIT, or the field of view is out of range.
     */
    public function __construct(
        public float $x,
        public float $y,
        public float $angle = 0.0,
        public float $fov = self::FOV,
    ) {
        foreach (['x' => $x, 'y' => $y] as $what => $value) {
            if (! is_finite($value)) {
                throw RaycastException::notFinite("A camera's {$what}");
            }
            if (abs($value) > Raycaster::LIMIT) {
                throw RaycastException::pastLimit("A camera's {$what}", $value);
            }
        }
        if (! is_finite($angle)) {
            throw RaycastException::notFinite("A camera's angle");
        }
        if (! ($fov > 0.0 && $fov < M_PI)) {
            throw RaycastException::fieldOfView($fov);
        }
    }

    /** @return array{float, float} The unit vector it looks along. */
    public function direction(): array
    {
        return [cos($this->angle), sin($this->angle)];
    }

    /**
     * Half the screen's width as a vector, at right angles to the direction: the column at the left
     * edge looks along direction - plane, the one at the right edge along direction + plane.
     *
     * @return array{float, float}
     */
    public function plane(): array
    {
        $half = tan($this->fov / 2);

        return [-sin($this->angle) * $half, cos($this->angle) * $half];
    }

    /** The same eye somewhere else. */
    public function at(float $x, float $y): self
    {
        return new self($x, $y, $this->angle, $this->fov);
    }

    /** The same eye looking another way. */
    public function facing(float $angle): self
    {
        return new self($this->x, $this->y, $angle, $this->fov);
    }
}
