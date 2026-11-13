<?php

namespace Surface\VelvetGE\Raycasting;

use RaycastGrid;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Rendering\Raycasting\Raycaster;
use ValueError;

/**
 * The casting in C: one RaycastGrid of ext-raycast. Every operation is one call into the
 * extension; what it refuses with ValueError is rethrown as RaycastException.
 */
class CLandRaycaster extends Raycaster
{
    private RaycastGrid $native;

    public function __construct(Grid $grid)
    {
        parent::__construct($grid);
        $this->native = new RaycastGrid($grid->width, $grid->height, $grid->cells);
    }

    public function driver(): string
    {
        return 'ext';
    }

    protected function ray(float $x, float $y, float $dx, float $dy, float $reach): string
    {
        try {
            return $this->native->cast($x, $y, $dx, $dy, $reach);
        } catch (ValueError $e) {
            throw new RaycastException($e->getMessage(), previous: $e);
        }
    }

    protected function rays(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $columns, float $reach): string
    {
        try {
            return $this->native->fan($x, $y, $dirX, $dirY, $planeX, $planeY, $columns, $reach);
        } catch (ValueError $e) {
            throw new RaycastException($e->getMessage(), previous: $e);
        }
    }

    protected function picture(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $width, int $height, array $walls, int $ceiling, int $floor, int $shade, float $reach): string
    {
        try {
            return $this->native->view($x, $y, $dirX, $dirY, $planeX, $planeY, $width, $height, $walls, $ceiling, $floor, $shade, $reach);
        } catch (ValueError $e) {
            throw new RaycastException($e->getMessage(), previous: $e);
        }
    }
}
