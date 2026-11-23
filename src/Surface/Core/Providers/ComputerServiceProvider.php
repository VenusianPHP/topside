<?php

namespace Surface\Core\Providers;

use Voyager\NutsAndBolts\ServiceProvider;
use Surface\Core\Console\SharpCacheCommand;

/**
 * The computer commands that come with venusian/topside, rather than with any one of its components.
 */
class ComputerServiceProvider extends ServiceProvider
{
    /**
     * The commands to be registered.
     *
     * @var array<string, class-string>
     */
    protected array $commands = [
        'SharpCache' => SharpCacheCommand::class,
    ];

    public function register(): void
    {
        $this->registerCommands($this->commands);
    }

    /**
     * @param array<string, class-string> $commands
     */
    protected function registerCommands(array $commands): void
    {
        // The console's lazy loader lists only the command classes the container has bound.
        foreach ($commands as $command) {
            $this->app->registerSingleton($command);
        }

        $this->commands(array_values($commands));
    }
}
