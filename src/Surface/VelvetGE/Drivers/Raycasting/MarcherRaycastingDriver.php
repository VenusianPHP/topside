<?php

namespace Surface\VelvetGE\Drivers\Raycasting;

use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Marcher;
use Surface\Contracts\Rendering\Raycasting\Raycaster;
use Surface\Rendering\Raycasting\RaycastingDriver;
use Surface\VelvetGE\Raycasting\MarcherRaycaster;

/**
 * A raycasting driver of VelvetGE: it says how its marcher is made, and that is all. Checking,
 * the camera's arithmetic and the way a view reaches a framebuffer come from VelvetGE.
 * VelvetGE's own two drivers are this class and a marcher, and so is a library binding:
 *
 *     class EmbreeRaycastingDriver extends MarcherRaycastingDriver
 *     {
 *         public function __construct()
 *         {
 *             if (! extension_loaded('embree')) {
 *                 throw RaycastException::extensionMissing('4.0');   // 'auto' then tries the next driver
 *             }
 *         }
 *
 *         public function driver(): string { return 'embree'; }
 *
 *         protected function marcher(Grid $grid): Marcher { return new EmbreeMarcher($grid); }
 *     }
 *
 * registered in its provider's boot(), when raycasting is on:
 *
 *     Raycasters::manager()->extend('embree', fn () => new EmbreeRaycastingDriver());
 *
 * A marcher is Marcher, and DirectMarcher too when it can write a view straight into a pixel
 * store's memory. VelvetGE's DirectDraw picks the way for each framebuffer, so a marcher never
 * names a store.
 */
abstract class MarcherRaycastingDriver extends RaycastingDriver
{
    abstract protected function marcher(Grid $grid): Marcher;

    public function caster(Grid $grid): Raycaster
    {
        return new MarcherRaycaster($this->driver(), $this->marcher($grid), $grid);
    }
}
