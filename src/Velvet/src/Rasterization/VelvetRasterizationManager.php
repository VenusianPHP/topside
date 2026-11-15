<?php

namespace VelvetGE\Rasterization;

use ReflectionException;
use Voyager\NutsAndBolts\Manager;

/**
 * Velvet's rasterization modules, by name. Velvet ships none: each module's service provider
 * registers its drivers here with extend().
 */
class VelvetRasterizationManager extends Manager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return config('velvet.cpu.rasterizers.default', 'lustrous-php');
    }
}
