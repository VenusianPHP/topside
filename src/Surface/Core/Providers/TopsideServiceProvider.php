<?php

namespace Surface\Core\Providers;

use Surface\Graphics\GraphicsServiceProvider;
use Surface\HumanInput\HumanInputServiceProvider;
use Surface\Rendering\RenderingServiceProvider;
use Surface\Toolkits\ToolkitsServiceProvider;
use Surface\VelvetGE\VelvetGEServiceProvider;
use Voyager\NutsAndBolts\AggregateServiceProvider;

class TopsideServiceProvider extends AggregateServiceProvider
{
    protected array $providers = [
        ToolkitsServiceProvider::class,
        RenderingServiceProvider::class,
        GraphicsServiceProvider::class,
        HumanInputServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->configs() as $key => $path) {
            $this->mergeConfigFrom($path, $key);
        }

        parent::register();
        signal('topside-booted');
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
            'av' => "{$dir}/av.php",
            'graphics' => "{$dir}/graphics.php",
            'human-input' => "{$dir}/human-input.php",
            'rendering' => "{$dir}/rendering.php",
            'toolkits' => "{$dir}/toolkits.php",
            'velvet' => "{$dir}/velvet.php",
            'windows' => "{$dir}/windows.php",
            'main.presentation' => "{$dir}/presentation.php",
        ];
    }
}