<?php

namespace Surface\Core\Actions\Bootstrap;

use Surface\Contracts\Toolkits\Mail\QuitRequested;
use Voyager\Contracts\Vessel\TheServiceContainer as Application;

class LoadDeathWatcher
{
    public function bootstrap(Application $app): void
    {
        $quit = fn () => $app['event-loop']->stop(0);

        $app['signals']->listen(QuitRequested::class, $quit);
        $app['signals']->listen('system.closed.application', $quit);
        $app['signals']->listen('system.close-requested.application', $quit);
    }
}