<?php

namespace Surface\Graphics;

use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class GraphicsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->registerSingleton('gfx', fn (FrameworkCore $app) => new GraphicsEngineDeviceManager($app));
        $this->app->alias('gfx', GraphicsEngineDeviceManager::class);
    }
}
