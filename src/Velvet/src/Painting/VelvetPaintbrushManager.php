<?php

namespace VelvetGE\Painting;

use ReflectionException;
use Voyager\NutsAndBolts\Manager;

/**
 * Velvet's painting modules, by name. Velvet ships none: each module's service provider
 * registers its drivers here with extend().
 */
class VelvetPaintbrushManager extends Manager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return config('velvet.cpu.painters.default', 'auto');
    }
}
