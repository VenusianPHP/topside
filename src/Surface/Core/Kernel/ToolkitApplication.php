<?php

namespace Surface\Core\Kernel;

use ReflectionException;
use Surface\Toolkits\Presentation;
use Voyager\Core\ApplicationKernel;
use Surface\Core\Actions\Bootstrap\LoadSignals;
use Voyager\Contracts\NutsAndBolts\ApplicationShell;
use Surface\Core\Actions\Bootstrap\LoadDeathWatcher;

class ToolkitApplication extends ApplicationKernel
{
    protected array $surface_bootstrappers = [
        LoadDeathWatcher::class,
        LoadSignals::class
    ];

    public function bootstrappers(): array
    {
        return [
            ...$this->bootstrappers,
            ...$this->surface_bootstrappers,
            ...$this->app_bootstrappers,
        ];
    }

    protected function run(): int
    {
        try {
            return parent::run();
        } finally {
            $this->releaseVisualOutputs();
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function activate(): void
    {
        $this->app->make(ApplicationShell::class)->open();
        Presentation::driver()->navigator()->go('/');
        Presentation::driver()->present();
    }

    protected function startup(): void {}

    protected function shutdown(): void {}

    protected function releaseVisualOutputs(): void
    {
        if ($this->app->isResolved('toolkit-adapters')) {

        }
    }
}