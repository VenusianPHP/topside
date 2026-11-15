<?php

declare(strict_types=1);

use Surface\Graphics\GraphicsEngineDeviceManager;

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
        ->extend('native', fn () => new \Surface\Rendering\Recording\Drivers\PHPLandRecordingDriver())
        ->extend('ext', fn () => new \Surface\Rendering\Recording\Drivers\CLandRecordingDriver());
}

/**
 * The recorder the fixtures that only record are given: VelvetGE's over its queue in PHP, or the
 * driver TOPSIDE_RECORDER names, so the same tests can be run against each driver.
 */
function recorderUnderTest(): \Surface\Contracts\Rendering\Recording\Recorder
{
    return recorders()->driver(getenv('TOPSIDE_RECORDER') ?: 'native')->recorder();
}

/** A graphics manager reading the given config, without a container. */
function gfx(array $config = []): GraphicsEngineDeviceManager
{
    return new class($config) extends GraphicsEngineDeviceManager {
        public function __construct(array $config)
        {
            $this->config = fakeConfig($config);
        }
    };
}
