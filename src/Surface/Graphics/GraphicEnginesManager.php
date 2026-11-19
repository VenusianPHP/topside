<?php

namespace Surface\Graphics;

use Surface\Contracts\Graphics\GraphicsEngine;
use Surface\Contracts\Graphics\GraphicsException;
use Voyager\NutsAndBolts\Manager;

/**
 * The graphics engines, by the name each engine's package registers. It has none of its own. A package registers a
 * creator that takes the container and whether the GPU is asked for: extend('velvet', fn ($app, bool $gpu) => ...).
 * One engine is made per name and per gpu.
 */
class GraphicEnginesManager extends Manager
{
    /**
     * @param string|null $name - the configured default (graphics.engine.default) when null
     * @param bool|null $gpu - the configured default (graphics.engine.gpu) when null
     *
     * @throws GraphicsException when no name is configured, the name is not registered, or the engine refuses $gpu
     */
    public function engine(?string $name = null, ?bool $gpu = null): GraphicsEngine
    {
        $name ??= $this->getDefaultDriver() ?? throw GraphicsException::noneConfigured();
        $gpu ??= (bool) $this->config->get('graphics.engine.gpu', false);
        $creator = $this->customCreators[$name] ?? throw GraphicsException::notRegistered($name, array_keys($this->customCreators));

        return $this->drivers[$name.':'.($gpu ? 'gpu' : 'cpu')] ??= $creator($this->vessel, $gpu);
    }

    /** The engine of that name, with the configured gpu. */
    public function driver(?string $driver = null): GraphicsEngine
    {
        return $this->engine($driver);
    }

    public function getDefaultDriver(): ?string
    {
        $name = $this->config->get('graphics.engine.default');

        return is_string($name) && $name !== '' ? $name : null;
    }
}
