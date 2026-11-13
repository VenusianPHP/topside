<?php

namespace Surface\Core\Actions\Bootstrap;

use ReflectionException;
use Surface\Contracts\Toolkits\Mail\QuitRequested;
use Voyager\Contracts\Vessel\TheServiceContainer as Application;

class LoadSignals
{
    /**
     * @throws ReflectionException
     */
    public function bootstrap(Application $app): void
    {
        // @todo - make this togglable via main.presentation
        $name = config('app.name', 'Venusian');
        $app['signals']->listen( "window.closed.$name", fn() => signal(new QuitRequested()));
    }
}
