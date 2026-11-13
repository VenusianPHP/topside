<?php

declare(strict_types=1);

use Surface\Rendering\Framebuffers\FramebufferLibraryManager;
use Surface\Rendering\Rasterization\RasterizationLibraryManager;
use Surface\Rendering\Raycasting\RaycastingLibraryManager;
use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Rasterization\CLandRasterizationDriver;
use Surface\VelvetGE\Drivers\Rasterization\PHPLandRasterizationDriver;
use Surface\VelvetGE\Drivers\Raycasting\CLandRaycastingDriver;
use Surface\VelvetGE\Drivers\Raycasting\PHPLandRaycastingDriver;

/** A config repository over an array, for managers built without a container. */
function fakeConfig(array $items): object
{
    return new class($items) {
        public function __construct(private readonly array $items) {}

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->items[$key] ?? $default;
        }
    };
}

/**
 * A framebuffers manager reading the given config, without a container, with VelvetGE's two
 * drivers registered as VelvetGEServiceProvider registers them.
 */
function framebuffers(array $config = []): FramebufferLibraryManager
{
    $manager = new class($config) extends FramebufferLibraryManager {
        public function __construct(array $config)
        {
            $this->config = fakeConfig($config);
        }

        /** No container here: a creator is called with nothing. */
        protected function callCustomCreator(string $driver): mixed
        {
            return $this->customCreators[$driver]();
        }
    };

    return $manager
        ->extend('native', fn () => new PHPLandFramebufferDriver())
        ->extend('ext', fn () => new CLandFramebufferDriver());
}

/**
 * A raycasters manager reading the given config, without a container, with VelvetGE's two
 * drivers registered as VelvetGEServiceProvider registers them.
 */
function raycasters(array $config = []): RaycastingLibraryManager
{
    $manager = new class($config) extends RaycastingLibraryManager {
        public function __construct(array $config)
        {
            $this->config = fakeConfig($config);
        }

        /** No container here: a creator is called with nothing. */
        protected function callCustomCreator(string $driver): mixed
        {
            return $this->customCreators[$driver]();
        }
    };

    return $manager
        ->extend('native', fn () => new PHPLandRaycastingDriver())
        ->extend('ext', fn () => new CLandRaycastingDriver());
}

/**
 * A rasterization manager reading the given config, without a container, with VelvetGE's two
 * drivers registered as VelvetGEServiceProvider registers them.
 */
function rasterize(array $config = []): RasterizationLibraryManager
{
    $manager = new class($config) extends RasterizationLibraryManager {
        public function __construct(array $config)
        {
            $this->config = fakeConfig($config);
        }

        /** No container here: a creator is called with nothing. */
        protected function callCustomCreator(string $driver): mixed
        {
            return $this->customCreators[$driver]();
        }
    };

    return $manager
        ->extend('native', fn () => new PHPLandRasterizationDriver())
        ->extend('ext', fn () => new CLandRasterizationDriver());
}
