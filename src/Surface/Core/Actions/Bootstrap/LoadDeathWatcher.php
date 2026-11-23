<?php

namespace Surface\Core\Actions\Bootstrap;

use Voyager\Contracts\Signals\SignalDispatcher;
use Surface\Contracts\Toolkits\Mail\QuitRequested;
use Voyager\Contracts\Vessel\TheServiceContainer as Application;

class LoadDeathWatcher
{
    public function bootstrap(Application $app): void
    {
        $quit = fn () => $app['event-loop']->stop(0);
        /** @var SignalDispatcher $signals */
        $signals = $app['signals'];

        $signals->listen(QuitRequested::class, $quit);
        $signals->listen('system.closed.application', $quit);
        $signals->listen('system.close-requested.application', $quit);
    }
}