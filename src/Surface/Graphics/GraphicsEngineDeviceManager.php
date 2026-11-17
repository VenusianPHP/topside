<?php

namespace Surface\Graphics;

use Closure;
use Surface\Contracts\Graphics\Backend;
use Surface\Contracts\Graphics\GraphicsEngine;
use Surface\Contracts\Graphics\GraphicsException;
use Voyager\NutsAndBolts\Manager;

/**
 * The graphics engines the application has, by name. It names none itself: each engine's package
 * registers its own with extend(), with the backends it offers.
 *
 * An engine is made once for each backend and kept: it is a connection to its library.
 */
class GraphicsEngineDeviceManager extends Manager
{
    /** @var array<string, list<Backend>> what each engine offers, in the order it was registered with */
    protected array $backends = [];

    /** @var array<string, GraphicsEngine> "name|backend" => the engine made for it */
    protected array $engines = [];

    public function getDefaultDriver(): ?string
    {
        return $this->config->get('graphics.engine.default', 'velvet');
    }

    /** 'cpu', 'gpu', or 'auto': the GPU where the engine has one, otherwise the CPU. */
    public function getDefaultBackend(): string
    {
        return $this->config->get('graphics.engine.backend', 'auto');
    }

    /**
     * @param string|null $name null: the default engine
     * @param string|null $backend 'cpu', 'gpu' or 'auto'; null: the default backend
     *
     * @throws GraphicsException When no engine has that name, or it does not offer that backend.
     */
    public function engine(?string $name = null, ?string $backend = null): GraphicsEngine
    {
        $name ??= $this->getDefaultDriver();
        if (! isset($this->customCreators[$name])) {
            throw GraphicsException::unknownEngine((string) $name, $this->engines());
        }
        $chosen = $this->backendFor($name, $backend ?? $this->getDefaultBackend());

        return $this->engines["{$name}|{$chosen->value}"] ??= $this->customCreators[$name]($chosen);
    }

    /** The engine of that name on the default backend. */
    public function driver(?string $driver = null): mixed
    {
        return $this->engine($driver);
    }

    /**
     * @param Closure(Backend): GraphicsEngine $callback makes the engine for the backend it is given
     * @param Backend ...$backends what the engine offers
     *
     * @throws GraphicsException When no backend is named.
     */
    public function extend(string $driver, Closure $callback, Backend ...$backends): static
    {
        if ($backends === []) {
            throw GraphicsException::noBackends($driver);
        }
        $this->backends[$driver] = array_values($backends);

        return parent::extend($driver, $callback);
    }

    /** @return list<string> the names registered */
    public function engines(): array
    {
        return array_keys($this->customCreators);
    }

    /** @return list<Backend> what that engine offers; [] for a name not registered */
    public function backends(string $name): array
    {
        return $this->backends[$name] ?? [];
    }

    /** Lets go of every engine made, so the next engine() makes a new one. */
    public function forgetDrivers(): static
    {
        foreach ($this->engines as $engine) {
            $engine->release();
        }
        $this->engines = [];

        return parent::forgetDrivers();
    }

    /** @throws GraphicsException */
    protected function backendFor(string $name, string $asked): Backend
    {
        $offered = $this->backends[$name];
        if ($asked === 'auto') {
            return in_array(Backend::GPU, $offered, true) ? Backend::GPU : $offered[0];
        }
        $backend = Backend::tryFrom($asked) ?? throw GraphicsException::unknownBackend($asked);
        if (! in_array($backend, $offered, true)) {
            throw GraphicsException::backendNotOffered($name, $backend, $offered);
        }

        return $backend;
    }
}
