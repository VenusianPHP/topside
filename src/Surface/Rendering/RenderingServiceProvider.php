<?php

namespace Surface\Rendering;

use ReflectionException;
use Surface\Rendering\Framebuffers\FramebufferLibraryManager;
use Surface\Rendering\Painters\PaintingLibraryManager;
use Surface\Rendering\Rasterization\RasterizationLibraryManager;
use Surface\Rendering\Raycasting\RaycastingLibraryManager;
use Surface\Rendering\Recording\RecordingLibraryManager;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class RenderingServiceProvider extends ServiceProvider
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->app->registerSingleton('rasterize', fn (FrameworkCore $app) => new RasterizationLibraryManager($app));
        $this->app->alias('rasterize', RasterizationLibraryManager::class);

        $this->app->registerSingleton('framebuffers', fn (FrameworkCore $app) => new FramebufferLibraryManager($app));
        $this->app->alias('framebuffers', FramebufferLibraryManager::class);

        $this->app->registerSingleton('painters', fn (FrameworkCore $app) => new PaintingLibraryManager($app));
        $this->app->alias('painters', PaintingLibraryManager::class);

        $this->app->registerSingleton('recorders', fn (FrameworkCore $app) => new RecordingLibraryManager($app));
        $this->app->alias('recorders', RecordingLibraryManager::class);

        // Opt-in: config/rendering.php raycaster.enabled.
        if (Raycasters::enabled()) {
            $this->app->registerSingleton('raycasters', fn (FrameworkCore $app) => new RaycastingLibraryManager($app));
            $this->app->alias('raycasters', RaycastingLibraryManager::class);
        }
    }

    public function boot(): void
    {

    }
}