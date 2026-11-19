<?php

namespace VelvetGE\Providers;

use Surface\Graphics\GraphicEnginesManager;
use VelvetGE\VelvetDevice;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class VelvetGEServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving('gfx', function (GraphicEnginesManager $gfx): void {
            $gfx->extend('velvet', fn (FrameworkCore $app, bool $gpu) => new VelvetDevice(
                $app->make('rendering.frames'),
                $app->make('rendering.drawers'),
                $app->make('rendering.recorders'),
                $gpu,
            ));
        });
        signal('velvet-ge-loaded');
    }
}
