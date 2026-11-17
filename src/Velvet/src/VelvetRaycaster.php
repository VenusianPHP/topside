<?php

namespace VelvetGE;

use Surface\Contracts\Graphics\DrawingSurface;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Graphics\Raycaster;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Hit;
use Surface\Contracts\Rendering\Raycasting\Raycaster as VelvetsRaycaster;
use Surface\Contracts\Rendering\Raycasting\Scenery;

/** Velvet's raycaster as the Graphics component hands it out: the one Velvet's raycasting module makes, painting onto Velvet's surfaces. */
class VelvetRaycaster implements Raycaster
{
    public function __construct(protected readonly VelvetsRaycaster $inner) {}

    public function grid(): Grid
    {
        return $this->inner->grid();
    }

    public function cast(float $x, float $y, float $angle, float $reach = INF): ?Hit
    {
        return $this->inner->cast($x, $y, $angle, $reach);
    }

    public function casts(float $x, float $y, array $angles, float $reach = INF): array
    {
        return array_map(fn (float $angle): ?Hit => $this->inner->cast($x, $y, $angle, $reach), $angles);
    }

    public function fan(Camera $camera, int $columns, float $reach = INF): string
    {
        return $this->inner->fan($camera, $columns, $reach);
    }

    public function paint(Camera $camera, Scenery $scenery, DrawingSurface $onto, ?Region $where = null, float $reach = INF): static
    {
        if (! $onto instanceof VelvetSurface) {
            throw GraphicsException::foreignResource('velvet', $onto->engine()->name());
        }
        // a frame of the surface's that the painter does not draw: the surface sees to its framebuffer's kind
        $onto->frame(fn ($framebuffer) => $this->inner->paint($camera, $scenery, $framebuffer, $where, $reach));

        return $this;
    }
}
