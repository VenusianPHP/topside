<?php

namespace VelvetGE\Framebuffers;

use ReflectionException;
use Voyager\NutsAndBolts\Manager;

/**
 * Velvet's framebuffer modules, by name. Velvet ships none: each module's service provider
 * registers its drivers here with extend().
 */
class VelvetFramebufferManager extends Manager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return config('velvet.cpu.framebuffers.default', 'lustrous-php-full');
    }
}
