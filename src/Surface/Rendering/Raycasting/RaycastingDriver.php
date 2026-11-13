<?php

namespace Surface\Rendering\Raycasting;

use Surface\Contracts\Rendering\Raycasting\RaycastDriver;

/**
 * What the raycasters manager hands out. Every driver extends it: VelvetGE's two, and each
 * library binding's.
 */
abstract class RaycastingDriver implements RaycastDriver
{

}
