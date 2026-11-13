<?php

namespace Surface\Toolkits;

use ReflectionException;
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
}