<?php

namespace Surface\VelvetGE;

use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Hit;
use Surface\Contracts\Rendering\Raycasting\Raycaster;
use Surface\Contracts\Rendering\Raycasting\Scenery;
use Surface\Graphics\GEFramebuffer;
use Surface\Graphics\GERaycaster;
use Surface\Graphics\GraphicsEngine;

/** Velvet's raycaster: Rendering's, from whichever raycasting driver the application has. */
class VelvetGERaycaster extends GERaycaster
{
    public function __construct(
        GraphicsEngine $engine,
        protected readonly Raycaster $inner,
    ) {
        parent::__construct($engine);
    }

    public function grid(): Grid
    {
        return $this->inner->grid();
    }

    public function cast(float $x, float $y, float $angle, float $reach = INF): ?Hit
    {
        return $this->inner->cast($x, $y, $angle, $reach);
    }

    public function fan(Camera $camera, int $columns, float $reach = INF): string
    {
        return $this->inner->fan($camera, $columns, $reach);
    }

    protected function paintView(Camera $camera, Scenery $scenery, GEFramebuffer $onto, Region $where, float $reach): void
    {
        /** @var VelvetGEFramebuffer $onto the base checked it is this engine's */
        $this->inner->paint($camera, $scenery, $onto->inner(), $where, $reach);
    }
}
