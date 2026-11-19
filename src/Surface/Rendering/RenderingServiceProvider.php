<?php

namespace Surface\Rendering;

use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;
use Surface\Rendering\Managers\FrameManager;
use Surface\Rendering\Managers\DrawerManager;
use Surface\Rendering\Managers\DrawCommandManager;

class RenderingServiceProvider extends ServiceProvider
{
    /**
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->registerDrawCommandManager();
        $this->registerFrameManager();
        $this->registerDrawerManager();
    }

    /**
     * @throws ReflectionException
     */
    protected function registerDrawerManager(): void
    {
        $this->app->registerSingleton('rendering.drawers', fn (FrameworkCore $app) => new DrawerManager($app));
        $this->app->alias('rendering.drawers', DrawerManager::class);
    }

    /**
     * @throws ReflectionException
     */
    protected function registerDrawCommandManager(): void
    {
        $this->app->registerSingleton('rendering.recorders', fn (FrameworkCore $app) => new DrawCommandManager($app));
        $this->app->alias('rendering.recorders', DrawCommandManager::class);
    }

    /**
     * @throws ReflectionException
     */
    protected function registerFrameManager(): void
    {
        $this->app->registerSingleton('rendering.frames', fn (FrameworkCore $app) => new FrameManager($app));
        $this->app->alias('rendering.frames', FrameManager::class);
    }
}
