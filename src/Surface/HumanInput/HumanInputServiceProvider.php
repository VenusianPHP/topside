<?php

namespace Surface\HumanInput;

use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class HumanInputServiceProvider extends ServiceProvider
{
    /**
     * Opt-in: config/human-input.php enabled. Off, nothing is registered, and no package can
     * register a backend.
     *
     * @throws ReflectionException
     */
    public function register(): void
    {
        if (! HumanInput::enabled()) {
            return;
        }

        $this->app->registerSingleton('human-input', fn (FrameworkCore $app) => new HumanInputManager($app));
        $this->app->alias('human-input', HumanInputManager::class);
    }
}
