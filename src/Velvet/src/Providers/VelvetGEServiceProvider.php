<?php

namespace VelvetGE\Providers;

use ReflectionException;
use Surface\Rendering\Framebuffers;
use Surface\Rendering\Painters;
use Surface\Rendering\Rasterization;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Contracts\Core\FrameworkCore;
use VelvetGE\Framebuffers\TopsideFramebufferDriver;
use VelvetGE\Framebuffers\VelvetFramebufferManager;
use VelvetGE\Rasterization\TopsideRasterizationDriver;
use VelvetGE\Rasterization\VelvetRasterizationManager;
use VelvetGE\Painting\TopsidePaintbrushDriver;
use VelvetGE\Painting\VelvetPaintbrushManager;
use VelvetGE\Raycasting\VelvetRaycastingManager;

class VelvetGEServiceProvider extends ServiceProvider
{
    /**
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/velvet.php', 'velvet');

        $this->bootstrapFramebufferService();
        $this->bootstrapRasterizerService();
        $this->bootstrapPaintbrushService();
        $this->boostrapAdvanceRenderingServices();

        signal('velvet-ge-loaded');
    }

    /**
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $this->publishes([__DIR__.'/../../config/velvet.php' => config_path('velvet.php')], 'velvet-config');
    }

    /**
     * @throws ReflectionException
     */
    protected function bootstrapFramebufferService(): void
    {
        Framebuffers::manager()->extend('velvet', fn() => new TopsideFramebufferDriver());

        $this->app->registerSingleton('velvet.framebuffers', fn (FrameworkCore $app) => new VelvetFramebufferManager($app));
        $this->app->alias('velvet.framebuffers', VelvetFramebufferManager::class);
    }

    /**
     * @throws ReflectionException
     */
    protected function bootstrapRasterizerService(): void
    {
        Rasterization::manager()->extend('velvet', fn() => new TopsideRasterizationDriver());

        $this->app->registerSingleton('velvet.rasterizers', fn (FrameworkCore $app) => new VelvetRasterizationManager($app));
        $this->app->alias('velvet.rasterizers', VelvetRasterizationManager::class);
    }

    /**
     * @throws ReflectionException
     */
    protected function bootstrapPaintbrushService(): void
    {
        Painters::manager()->extend('velvet', fn() => new TopsidePaintbrushDriver());

        $this->app->registerSingleton('velvet.painters', fn (FrameworkCore $app) => new VelvetPaintbrushManager($app));
        $this->app->alias('velvet.painters', VelvetPaintbrushManager::class);
    }

    /**
     * Velvet's own techniques, beyond framebuffers, rasterization and painting. Each has its own
     * manager here, which modules register on; none is registered on Rendering.
     *
     * @throws ReflectionException
     */
    protected function boostrapAdvanceRenderingServices(): void
    {
        $this->app->registerSingleton('velvet.raycasters', fn (FrameworkCore $app) => new VelvetRaycastingManager($app));
        $this->app->alias('velvet.raycasters', VelvetRaycastingManager::class);
    }
}
