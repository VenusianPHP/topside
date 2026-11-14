<?php

namespace Surface\VelvetGE;

use ReflectionException;
use Surface\Contracts\Graphics\Backend;
use Surface\Graphics\Gfx;
use Surface\Rendering\Framebuffers;
use Surface\Rendering\Painters;
use Surface\Contracts\Rendering\Rasterization\RasterizeException;
use Surface\Rendering\Rasterization;
use Surface\Rendering\Rasterization\Stroker;
use Surface\Rendering\Raycasters;
use Surface\Rendering\Recorders;
use Surface\Rendering\Rasterization\RasterizationLibraryManager;
use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Painting\CLandPaintingDriver;
use Surface\VelvetGE\Drivers\Painting\PHPLandPaintingDriver;
use Surface\VelvetGE\Drivers\Rasterization\CLandRasterizationDriver;
use Surface\VelvetGE\Drivers\Rasterization\PHPLandRasterizationDriver;
use Surface\VelvetGE\Drivers\Raycasting\CLandRaycastingDriver;
use Surface\VelvetGE\Drivers\Raycasting\PHPLandRaycastingDriver;
use Surface\VelvetGE\Rasterize\CLandStroker;
use Surface\VelvetGE\Drivers\Recording\CLandRecordingDriver;
use Surface\VelvetGE\Drivers\Recording\PHPLandRecordingDriver;
use Voyager\NutsAndBolts\ServiceProvider;

class VelvetGEServiceProvider extends ServiceProvider
{
    /**
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->bootstrapRasterizer();
        $this->bootstrapStroker();
        $this->bootstrapRaycaster();
        $this->bootstrapFramebufferService();
        $this->bootstrapRecorder();
        $this->bootstrapPainter();
        $this->bootstrapEngine();
    }

    public function boot(): void
    {

    }

    /**
     * @throws ReflectionException
     */
    protected function bootstrapRasterizer(): void
    {
        Rasterization::manager()->extend('native', fn() => new PHPLandRasterizationDriver());
        Rasterization::manager()->extend('ext', fn() => new CLandRasterizationDriver());
    }

    /**
     * What strokes a polyline for the recorder, the rasterizer and the GPU lowering: config/rendering.php's
     * stroker.default. 'auto' takes ext-rasterize's when it has a stroker and PHP's otherwise, 'ext' insists
     * on ext-rasterize's, 'native' is PHP's.
     *
     * @throws RasterizeException When 'ext' is asked for and ext-rasterize cannot stroke.
     */
    protected function bootstrapStroker(): void
    {
        $wanted = config('rendering.stroker.default', 'auto');
        if ($wanted === 'native') {
            Stroker::use(null);

            return;
        }
        try {
            Stroker::use(new CLandStroker());
        } catch (RasterizeException $e) {
            if ($wanted === 'ext') {
                throw $e;
            }
            Stroker::use(null);
        }
    }

    /** Only when the application turned raycasting on: config/rendering.php raycaster.enabled. */
    protected function bootstrapRaycaster(): void
    {
        if (! Raycasters::enabled()) {
            return;
        }

        Raycasters::manager()->extend('native', fn() => new PHPLandRaycastingDriver());
        Raycasters::manager()->extend('ext', fn() => new CLandRaycastingDriver());
    }

    /**
     * @throws ReflectionException
     */
    protected function bootstrapFramebufferService(): void
    {
        Framebuffers::manager()->extend('native', fn() => new PHPLandFramebufferDriver());
        Framebuffers::manager()->extend('ext', fn() => new CLandFramebufferDriver());
    }


    /**
     * @throws ReflectionException
     */
    protected function bootstrapPainter(): void
    {
        Painters::manager()->extend('native', fn () => new PHPLandPaintingDriver());
        Painters::manager()->extend('ext', fn () => new CLandPaintingDriver());
    }

    /**
     * @throws ReflectionException
     */
    protected function bootstrapRecorder(): void
    {
        Recorders::manager()->extend('native', fn () => new PHPLandRecordingDriver());
        Recorders::manager()->extend('ext', fn () => new CLandRecordingDriver());
    }

    /**
     * Velvet as a graphics engine, on the CPU. Its GPU backend is not built: asking for it says so.
     *
     * @throws ReflectionException
     */
    protected function bootstrapEngine(): void
    {
        Gfx::manager()
            ->extend('velvet', fn (Backend $backend) => new VelvetDevice(
                $backend,
                Framebuffers::manager(),
                Rasterization::manager(),
                Painters::manager(),
                Raycasters::enabled() ? Raycasters::manager() : null,
                config('velvet.cpu', []),
            ), Backend::CPU)
            ->notReady('velvet', Backend::GPU);
    }
}
