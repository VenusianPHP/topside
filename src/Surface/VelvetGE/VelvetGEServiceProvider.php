<?php

namespace Surface\VelvetGE;

use ReflectionException;
use Surface\Rendering\Framebuffers;
use Surface\Rendering\Rasterization;
use Surface\Rendering\Raycasters;
use Surface\Rendering\Rasterization\RasterizationLibraryManager;
use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Rasterization\CLandRasterizationDriver;
use Surface\VelvetGE\Drivers\Rasterization\PHPLandRasterizationDriver;
use Surface\VelvetGE\Drivers\Raycasting\CLandRaycastingDriver;
use Surface\VelvetGE\Drivers\Raycasting\PHPLandRaycastingDriver;
use Voyager\NutsAndBolts\ServiceProvider;

class VelvetGEServiceProvider extends ServiceProvider
{
    /**
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->bootstrapRasterizer();
        $this->bootstrapRaycaster();
        $this->bootstrapFramebufferService();
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

}