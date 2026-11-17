<?php

namespace Surface\Rendering;

use ReflectionException;
use Surface\Rendering\Framebuffers\FramebufferLibraryManager;
use Surface\Rendering\Glyphs\GlyphLibraryManager;
use Surface\Rendering\Painters\PaintingLibraryManager;
use Surface\Rendering\Rasterization\RasterizationLibraryManager;
use Surface\Rendering\Stroking\StrokingLibraryManager;
use Surface\Rendering\Recording\RecordingLibraryManager;
use Surface\Rendering\Recording\Drivers\CLandRecordingDriver;
use Surface\Rendering\Recording\Drivers\PHPLandRecordingDriver;
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
        $this->bootstrapRecorder();
        $this->bootstrapStroker();
        $this->bootstrapFramebuffers();
        $this->bootstrapRasterization();
        $this->bootstrapPainters();
        $this->bootstrapGlyphs();
    }

    /**
     * @throws ReflectionException
     */
    protected function bootstrapRecorder(): void
    {
        $this->app->registerSingleton('recorders', fn (FrameworkCore $app) => new RecordingLibraryManager($app));
        $this->app->alias('recorders', RecordingLibraryManager::class);

        // Recording is Topside's, for every engine: its two drivers are registered here.
        $this->app->make('recorders')->extend('php', fn () => new PHPLandRecordingDriver());
        $this->app->make('recorders')->extend('c', fn () => new CLandRecordingDriver());
    }

    /**
     * Stroking is Topside's, for every engine: the recorder and the GPU engines stroke through it. It
     * brings no stroker of its own; packages register theirs here.
     *
     * @throws ReflectionException
     */
    protected function bootstrapStroker(): void
    {
        $this->app->registerSingleton('strokers', fn (FrameworkCore $app) => new StrokingLibraryManager($app));
        $this->app->alias('strokers', StrokingLibraryManager::class);
    }

    /**
     * @throws ReflectionException
     */
    protected function bootstrapFramebuffers(): void
    {
        $this->app->registerSingleton('framebuffers', fn (FrameworkCore $app) => new FramebufferLibraryManager($app));
        $this->app->alias('framebuffers', FramebufferLibraryManager::class);
    }

    /**
     * @throws ReflectionException
     */
    protected function bootstrapRasterization(): void
    {
        $this->app->registerSingleton('rasterize', fn (FrameworkCore $app) => new RasterizationLibraryManager($app));
        $this->app->alias('rasterize', RasterizationLibraryManager::class);
    }

    /**
     * @throws ReflectionException
     */
    protected function bootstrapPainters(): void
    {
        $this->app->registerSingleton('painters', fn (FrameworkCore $app) => new PaintingLibraryManager($app));
        $this->app->alias('painters', PaintingLibraryManager::class);
    }

    /**
     * Glyphs are drawn by the drivers packages register here; Rendering brings none of its own.
     *
     * @throws ReflectionException
     */
    protected function bootstrapGlyphs(): void
    {
        $this->app->registerSingleton('glyphs', fn (FrameworkCore $app) => new GlyphLibraryManager($app));
        $this->app->alias('glyphs', GlyphLibraryManager::class);
    }

    public function boot(): void
    {

    }
}