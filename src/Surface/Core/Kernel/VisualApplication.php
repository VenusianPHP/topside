<?php

namespace Surface\Core\Kernel;

use ReflectionException;
use Surface\Toolkits\View;
use Surface\Toolkits\Toolkits;
use Voyager\Core\ApplicationKernel;
use Surface\Toolkits\ToolkitBridge;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Core\Actions\Bootstrap\LoadSignals;
use Surface\Core\Actions\Bootstrap\LoadDeathWatcher;
use Surface\Core\Actions\Bootstrap\LoadRoutingMailListeners;
use Surface\Contracts\Toolkits\Drivers\ToolkitWindowDriver;
use Voyager\Contracts\NutsAndBolts\ApplicationShell;

abstract class VisualApplication extends ApplicationKernel
{
    protected array $surface_bootstrappers = [
        LoadDeathWatcher::class,
        LoadSignals::class,
        LoadRoutingMailListeners::class,
    ];

    public function bootstrappers(): array
    {
        return [
            ...$this->bootstrappers,
            ...$this->surface_bootstrappers,
            ...$this->app_bootstrappers,
        ];
    }

    protected function startup(): void {}

    /**
     * @throws ReflectionException
     */
    protected function activate(): void
    {
        $this->app->make(ApplicationShell::class)->open();
        $this->app->make(Navigator::class)->go('/');
        View::driver()->present();
    }

    protected function shutdown(): void {}

    protected function run(): int
    {
        try {
            return parent::run();
        } finally {
            $this->releaseVisualOutputs();
        }
    }

    /**
     * Release every visual output Surface opened, after the app's shutdown().
     */
    protected function releaseVisualOutputs(): void
    {

        try {
            /*if ($this->app->isResolved('displays')) {
                $this->app['displays']->destroy();
            }*/
        }
        finally {
            if ($this->app->isResolved('toolkit-bridge-manager')) {
                foreach (Toolkits::bridges() as $driver) {
                    if (! $driver instanceof ToolkitBridge) {
                        continue;
                    }

                    $session = $driver->session();
                    if (is_null($session) || ! $session->connected()) {
                        continue;
                    }

                    try {
                        /*if ($driver instanceof StagedWindowDriver) {
                            $driver->closeAllStaged();
                        }*/
                        if ($driver instanceof ToolkitWindowDriver) {
                            $driver->closeAll();
                        }
                    } finally {
                        $session->leaveLoop();
                        $session->disconnect();
                    }
                }
            }
        }

    }
}
