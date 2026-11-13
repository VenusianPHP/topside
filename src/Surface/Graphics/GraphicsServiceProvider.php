<?php

namespace Surface\Graphics;

use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class GraphicsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->registerSingleton('graphics', fn (FrameworkCore $app) => new GraphicsManager($app->make('config')));
        $this->app->alias('graphics', GraphicsManager::class);
    }
}
