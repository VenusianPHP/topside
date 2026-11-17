<?php

namespace Surface\Toolkits;

use ReflectionException;
use Surface\Graphics\Gfx;
use Surface\Contracts\Graphics\GraphicsEngine;
use Surface\Contracts\Graphics\GraphicsException;
use Voyager\Contracts\NutsAndBolts\ApplicationShell;

class ToolkitShell implements ApplicationShell
{
    /**
     * @throws ReflectionException
     */
    public function open(): void
    {
        [$width, $height] = config('main.presentation.window_size', [640, 480]);

        View::driver('toolkit')->boot($width, $height);
    }

    /**
     * The graphics engine this application's canvases are drawn with: the one the presentation
     * config names (PRESENTATION_CANVAS_DRIVER). Left unset, that is the Graphics default.
     *
     * The engine named is used alone: when it is not registered, this throws.
     *
     * @throws GraphicsException
     */
    public function canvasEngine(): GraphicsEngine
    {
        return Gfx::engine(config('main.presentation.toolkit.canvas_driver'));
    }
}