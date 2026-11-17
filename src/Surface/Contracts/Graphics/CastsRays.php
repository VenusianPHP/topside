<?php

namespace Surface\Contracts\Graphics;

use Surface\Contracts\Rendering\Raycasting\Grid;

/**
 * An engine that can cast rays: one whose has(Feature::RAY_CASTING) may be true. Not every engine
 * is one, so a caller asks both:
 *
 *     if ($engine instanceof CastsRays && $engine->has(Feature::RAY_CASTING)) {
 *         $caster = $engine->raycaster($grid);
 *     }
 */
interface CastsRays
{
    /** A raycaster over $grid. Asked of an engine whose has(Feature::RAY_CASTING) is false, it throws. */
    public function raycaster(Grid $grid): Raycaster;
}
