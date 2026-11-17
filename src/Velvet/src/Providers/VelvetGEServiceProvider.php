<?php

namespace VelvetGE\Providers;

use ReflectionException;
use Surface\Contracts\Graphics\Backend;
use Surface\Graphics\Gfx;
use Surface\Rendering\Framebuffers;
use Surface\Rendering\Glyphs;
use Surface\Rendering\Painters;
use Surface\Rendering\Rasterization;
use VelvetGE\VelvetDevice;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Contracts\Core\FrameworkCore;
use VelvetGE\Framebuffers\TopsideFramebufferDriver;
use VelvetGE\Framebuffers\VelvetFramebufferManager;
use VelvetGE\Rasterization\TopsideRasterizationDriver;
use VelvetGE\Rasterization\VelvetRasterizationManager;
use VelvetGE\Painting\TopsidePaintbrushDriver;
use VelvetGE\Painting\VelvetPaintbrushManager;
use VelvetGE\Raycasting\VelvetRaycastingManager;
use VelvetGE\Glyphs\TopsideGlyphDriver;
use VelvetGE\Glyphs\VelvetGlyphManager;

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
        $this->bootstrapGlyphService();
        $this->boostrapAdvanceRenderingServices();
        $this->registerGraphicsEngine();

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
     * @throws ReflectionException
     */
    protected function bootstrapGlyphService(): void
    {
        Glyphs::manager()->extend('velvet', fn() => new TopsideGlyphDriver());

        $this->app->registerSingleton('velvet.glyphs', fn (FrameworkCore $app) => new VelvetGlyphManager($app));
        $this->app->alias('velvet.glyphs', VelvetGlyphManager::class);
    }

    /**
     * Velvet as a graphics engine: what Gfx::engine('velvet') makes. It runs on the CPU only.
     *
     * @throws ReflectionException
     */
     protected function registerGraphicsEngine(): void
     {
        Gfx::extend('velvet', fn (Backend $backend) => new VelvetDevice($backend), Backend::CPU);
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
