<?php

namespace Surface\Graphics;

use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class GraphicsServiceProvider extends ServiceProvider
{
    /**
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->app->registerSingleton('gfx', fn (FrameworkCore $app) => new GraphicsEngineDeviceManager($app));
        $this->app->alias('gfx', GraphicsEngineDeviceManager::class);
    }
}
