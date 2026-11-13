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

/**
 * A painting manager reading the given config, without a container, with VelvetGE's two drivers
 * registered as VelvetGEServiceProvider registers them, over the native rasterization driver.
 */
function painters(array $config = []): \Surface\Rendering\Painters\PaintingLibraryManager
{
    $manager = new class($config) extends \Surface\Rendering\Painters\PaintingLibraryManager {
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
        ->extend('native', fn () => new \Surface\VelvetGE\Drivers\Painting\PHPLandPaintingDriver(new PHPLandRasterizationDriver(), true, new \Surface\VelvetGE\Drivers\Recording\PHPLandRecordingDriver()))
        ->extend('ext', fn () => new \Surface\VelvetGE\Drivers\Painting\CLandPaintingDriver(new PHPLandRasterizationDriver(), true, new \Surface\VelvetGE\Drivers\Recording\PHPLandRecordingDriver()));
}

/**
 * A recording manager reading the given config, without a container, with VelvetGE's drivers
 * registered as VelvetGEServiceProvider registers them.
 */
function recorders(array $config = []): \Surface\Rendering\Recording\RecordingLibraryManager
{
    $manager = new class($config) extends \Surface\Rendering\Recording\RecordingLibraryManager {
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
        ->extend('native', fn () => new \Surface\VelvetGE\Drivers\Recording\PHPLandRecordingDriver())
        ->extend('ext', fn () => new \Surface\VelvetGE\Drivers\Recording\CLandRecordingDriver());
}

/**
 * The recorder the fixtures that only record are given: VelvetGE's over its queue in PHP, or the
 * driver TOPSIDE_RECORDER names, so the same tests can be run against each driver.
 */
function recorderUnderTest(): \Surface\Contracts\Rendering\Recording\Recorder
{
    return recorders()->driver(getenv('TOPSIDE_RECORDER') ?: 'native')->recorder();
}
