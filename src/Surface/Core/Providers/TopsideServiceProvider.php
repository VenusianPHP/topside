<?php

namespace Surface\Core\Providers;

use Surface\Rendering\RenderingServiceProvider;
use Surface\Toolkits\ToolkitsServiceProvider;
use Surface\VelvetGE\VelvetGEServiceProvider;
use Voyager\NutsAndBolts\AggregateServiceProvider;

class TopsideServiceProvider extends AggregateServiceProvider
{
    protected array $providers = [
        ToolkitsServiceProvider::class,
        RenderingServiceProvider::class,
        VelvetGEServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->configs() as $key => $path) {
            $this->mergeConfigFrom($path, $key);
        }

        parent::register();
    }

    public function boot(): void
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

        return [
            'rendering' => "{$dir}/rendering.php",
            'toolkits' => "{$dir}/toolkits.php",
            'windows' => "{$dir}/windows.php",
            'main.presentation' => "{$dir}/presentation.php",
        ];
    }
}