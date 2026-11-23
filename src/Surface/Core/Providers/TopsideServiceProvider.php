<?php

namespace Surface\Core\Providers;

use Surface\Toolkits\ToolkitsServiceProvider;
use Voyager\NutsAndBolts\AggregateServiceProvider;

class TopsideServiceProvider extends AggregateServiceProvider
{
    protected array $providers = [
        ToolkitsServiceProvider::class,
        ComputerServiceProvider::class,
    ];

    protected array $configs = [
        'toolkits' => 'toolkits',
        'main.presentation' => 'presentation'
    ];

    public function register(): void
    {
        $this->mergeConfigs();

        parent::register();
    }

    public function boot(): void
    {
        $this->publishConfigs();
    }

    protected function mergeConfigs(): void
    {
        foreach ($this->configs() as $key => $path) {
            $this->mergeConfigFrom($path, $key);
        }
    }

    protected function publishConfigs(): void
    {
        $published = [];
        foreach ($this->configs() as $key => $path) {

            $published[$path] = $this->app->configPath("{$key}.php");
        }

        $this->publishes($published, 'surface-config');
    }

    protected function configs(): array
    {
        $dir = dirname(__DIR__, 4).'/config';

        return array_map(fn($path) => "{$dir}/{$path}.php", $this->configs);
    }
}