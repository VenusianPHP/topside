<?php

namespace Surface\Toolkits;

use ReflectionException;
use Voyager\NutsAndBolts\ServiceProvider;

/**
 * What a toolkit package's provider is: its name, its config file and its driver class.
 *
 * Registering builds nothing. The driver is made the first time toolkits.default (or a direct
 * ToolkitAdapters::driver() call) asks for it, so an installed toolkit whose extension or OS is
 * missing stays harmless until it is selected: one app can require AppKit and GTK and run
 * whichever its platform has.
 */
abstract class ToolkitAdapterServiceProvider extends ServiceProvider
{
    /**
     * The name toolkits.default selects this toolkit by: 'gtk', 'qt', ...
     */
    protected string $toolkit;

    /**
     * The driver class, built with the framework core.
     *
     * @var class-string<Drivers\ToolkitAdapterDriver>
     */
    protected string $driver;

    /**
     * The package's config file. Published as config/{toolkit}.php and merged under that key.
     */
    protected string $config;

    public function register(): void
    {

    }

    /**
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $source = realpath($this->config) ?: $this->config;
        $this->publishes([$source => $this->app->configPath("{$this->toolkit}.php")], "{$this->toolkit}-config");
        $this->mergeConfigFrom($source, $this->toolkit);

        $driver = $this->driver;
        ToolkitAdapters::extend($this->toolkit, fn ($app) => new $driver($app));
    }
}
