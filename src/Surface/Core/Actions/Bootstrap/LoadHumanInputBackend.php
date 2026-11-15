<?php

namespace Surface\Core\Actions\Bootstrap;

use ReflectionException;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputContext;
use Surface\HumanInput\HumanInputManager;
use Surface\HumanInput\HumanInputPoller;
use Voyager\Contracts\IOPools\Loop as LoopInterface;
use Voyager\Contracts\Vessel\TheServiceContainer as Application;

class LoadHumanInputBackend
{
    /**
     * When human input is on: the configured backend is asked whether it serves this application's
     * toolkit, canvas engine and OS, so one that cannot refuses here, as the application boots. The
     * pad source is made, and both poll on the event loop once it runs.
     *
     * @throws HumanInputException
     * @throws ReflectionException
     */
    public function bootstrap(Application $app): void
    {
        $enabled = config('human-input.enabled', false);
        if($enabled)
        {
            /** @var HumanInputManager $input */
            $input = $app->make('human-input');
            $input->resolve(new InputContext(
                toolkit: (string) config('main.presentation.toolkit.driver'),
                engine: (string) (config('main.presentation.toolkit.canvas_driver') ?? config('graphics.engine.default', 'velvet')),
                os: device_os_family(),
            ));
            $input->pads();

            $join = function (LoopInterface $loop) use ($app, $input): void {
                if (! $app->isApplicationRunning()) {
                    return;
                }
                $input->connect();
                HumanInputPoller::join($loop, $input);
            };
            $app->afterResolving('event-loop', $join);
            if ($app->isResolved('event-loop')) {
                $join($app->make('event-loop'));
            }
        }
    }
}
