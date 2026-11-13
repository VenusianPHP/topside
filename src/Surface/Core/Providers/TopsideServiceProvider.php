<?php

namespace Surface\Core\Providers;

use Surface\Graphics\GraphicsServiceProvider;
use Surface\Rendering\RenderingServiceProvider;
use Surface\Toolkits\ToolkitsServiceProvider;
use Surface\Typography\TypographyServiceProvider;
use Surface\VelvetGE\VelvetGEServiceProvider;
use Voyager\NutsAndBolts\AggregateServiceProvider;

class TopsideServiceProvider extends AggregateServiceProvider
{
    protected array $providers = [
        ToolkitsServiceProvider::class,
        RenderingServiceProvider::class,
        TypographyServiceProvider::class,
        GraphicsServiceProvider::class,
        // after Rendering and Graphics: it registers its drivers and its engine with their managers
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
            'graphics' => "{$dir}/graphics.php",
            'rendering' => "{$dir}/rendering.php",
            'toolkits' => "{$dir}/toolkits.php",
            'windows' => "{$dir}/windows.php",
            'main.presentation' => "{$dir}/presentation.php",
        ];
    }
}