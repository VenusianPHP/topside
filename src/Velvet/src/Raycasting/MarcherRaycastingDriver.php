<?php

namespace VelvetGE\Raycasting;

use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Marcher;
use Surface\Contracts\Rendering\Raycasting\RaycastDriver;
use Surface\Contracts\Rendering\Raycasting\Raycaster;

/**
 * A raycasting module of Velvet: it says how its marcher is made, and that is all. Checking, the
 * camera's arithmetic and the way a view reaches a framebuffer come from Velvet. A module's
 * driver is this class and a marcher:
 *
 *     class EmbreeRaycastingDriver extends MarcherRaycastingDriver
 *     {
 *         public function driver(): string { return 'embree'; }
 *
 *         protected function marcher(Grid $grid): Marcher { return new EmbreeMarcher($grid); }
 *     }
 *
 * registered on Velvet's raycasting manager from the module's provider:
 *
 *     app('velvet.raycasters')->extend('embree', fn () => new EmbreeRaycastingDriver());
 *
 * A marcher is Marcher, and DirectMarcher too when it can write a view straight into a
 * framebuffer's memory. Velvet's DirectDraw picks the way for each framebuffer, so a marcher never
 * names a framebuffer.
 */
abstract class MarcherRaycastingDriver implements RaycastDriver
{
    abstract protected function marcher(Grid $grid): Marcher;

    public function caster(Grid $grid): Raycaster
    {
        return new MarcherRaycaster($this->driver(), $this->marcher($grid), $grid);
    }
}
