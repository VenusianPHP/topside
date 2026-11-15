<?php

namespace VelvetGE\Raycasting;

use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Marcher;
use Surface\Contracts\Rendering\Raycasting\Scenery;
use VelvetGE\Rasterization\DirectDraw;
use Surface\Rendering\Raycasting\Raycaster;
use Surface\Contracts\Rendering\Framebuffers\InstanceThatManagesAFramebuffer;

/**
 * Velvet's raycaster: the one every raycasting module's driver mints, Lustrous's and any
 * other's. It works over the driver's marcher, and it puts a view on a framebuffer through
 * DirectDraw.
 */
class MarcherRaycaster extends Raycaster
{
    /** @param string $driver the minting driver's name */
    public function __construct(
        private readonly string $driver,
        private readonly Marcher $marcher,
        Grid $grid,
    ) {
        parent::__construct($grid);
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function paint(Camera $camera, Scenery $scenery, InstanceThatManagesAFramebuffer $target, ?Region $where = null, float $reach = INF): void
    {
        $where = self::inside($target, $where);

        DirectDraw::view($this->marcher, self::pictureOf($camera, $where->width, $where->height, $scenery, $reach), $target, $where);
    }

    protected function ray(float $x, float $y, float $dx, float $dy, float $reach): string
    {
        return $this->marcher->ray($x, $y, $dx, $dy, $reach);
    }

    protected function rays(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $columns, float $reach): string
    {
        return $this->marcher->rays($x, $y, $dirX, $dirY, $planeX, $planeY, $columns, $reach);
    }

    protected function picture(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $width, int $height, array $walls, int $ceiling, int $floor, int $shade, float $reach): string
    {
        return $this->marcher->picture($x, $y, $dirX, $dirY, $planeX, $planeY, $width, $height, $walls, $ceiling, $floor, $shade, $reach);
    }
}
