<?php

namespace VelvetGE\Raycasting;

use ReflectionException;
use Voyager\NutsAndBolts\Manager;

/**
 * Velvet's raycasting modules, by name. Velvet ships none: each module's service provider
 * registers its drivers here with extend(). Raycasting is Velvet's own: nothing is registered on
 * Rendering for it.
 */
class VelvetRaycastingManager extends Manager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return config('velvet.cpu.advanced.raycasting.default', 'auto');
    }
}
