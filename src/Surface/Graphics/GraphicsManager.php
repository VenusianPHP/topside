<?php

namespace Surface\Graphics;

use Closure;
use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Graphics\GraphicsEngine;
use Surface\Contracts\Rendering\Painting\Painter;

/**
 * The graphics engines an application has, by name. Each is made once, the first time it is asked
 * for, by the creator its package registered:
 *
 *     Engines::manager()->extend('metal', fn () => new MetalGraphicsEngine());
 *
 * 'velvet', the CPU engine, comes with Topside (VelvetGEServiceProvider registers it). config's
 * graphics.default names the engine asked for without a name.
 */
class GraphicsManager
{
    /** The packages the GPU engines come with. */
    protected const array PACKAGES = [
        'metal' => 'jovian/venusian-metal',
        'opengl' => 'jovian/venusian-opengl',
        'vulkan' => 'jovian/venusian-vulkan',
        'sdl3' => 'jovian/venusian-sdl3',
    ];

    /** @var array<string, Closure(self): GraphicsEngine> */
    protected array $creators = [];

    /** @var array<string, GraphicsEngine> */
    protected array $engines = [];

    /**
     * @param  \Voyager\Contracts\Config\Repository  $config
     */
    public function __construct(
        protected $config,
    ) {}

    public function getDefaultEngine(): string
    {
        return $this->config->get('graphics.default', 'velvet');
    }

    /**
     * The engine of that name. Null names config's default.
     *
     * @throws DrawingException When no engine of that name is registered.
     */
    public function engine(?string $name = null): GraphicsEngine
    {
        $name ??= $this->getDefaultEngine();
        if (isset($this->engines[$name])) {
            return $this->engines[$name];
        }
        if (! isset($this->creators[$name])) {
            $registered = implode(', ', $this->engines()) ?: 'none';
            $package = isset(self::PACKAGES[$name]) ? ' It comes with '.self::PACKAGES[$name].'.' : '';

            throw new DrawingException("No graphics engine named '{$name}' is registered (registered: {$registered}).{$package}");
        }

        return $this->engines[$name] = $this->creators[$name]($this);
    }

    /**
     * A new painter from an engine: engine($name)->painter($args).
     *
     * @param  array<string, mixed>  $args
     */
    public function painter(?string $name = null, array $args = []): Painter
    {
        return $this->engine($name)->painter($args);
    }

    /**
     * Register an engine, or replace one. A replaced engine that was already made is let go.
     *
     * @param  Closure(self): GraphicsEngine  $creator
     */
    public function extend(string $name, Closure $creator): static
    {
        $this->creators[$name] = $creator;
        unset($this->engines[$name]);

        return $this;
    }

    /** @return list<string> */
    public function engines(): array
    {
        return array_keys($this->creators);
    }
}
