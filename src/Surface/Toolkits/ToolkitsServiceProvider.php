<?php

namespace Surface\Toolkits;

use RuntimeException;
use ReflectionException;
use Surface\Toolkits\Views\Sharp;
use Surface\Toolkits\Navigation\Navigator;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;
use Surface\Toolkits\Managers\MenuManager;
use Surface\Toolkits\Managers\ToolkitWindowManager;
use Surface\Toolkits\Managers\ToolkitAdapterManager;
use Voyager\Contracts\IOPools\Loop as LoopInterface;
use Surface\Toolkits\Managers\PresentationTreeManager;
use Surface\Contracts\Toolkits\ToolkitAdapterDriver;

class ToolkitsServiceProvider extends ServiceProvider
{
    /**
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->registerSingletons();
        $this->registerSharp();
        $this->provisionEventLoop();
    }

    /**
     * Templates from resources/, components from App\Views, compiled to storage/framework/views.
     */
    protected function registerSharp(): void
    {
        $this->app->registerSingleton(Sharp::class, fn (FrameworkCore $app) => new Sharp(
            $app->basePath('resources'),
            $app->storagePath('framework/views'),
            $this->componentNamespace($app),
        ));
    }

    /**
     * App\Views, or null for an instance that is not an application (no composer.json).
     */
    protected function componentNamespace(FrameworkCore $app): ?string
    {
        if (! is_file($app->basePath('composer.json'))) {
            return null;
        }

        try {
            return rtrim($app->getNamespace(), '\\').'\\Views';
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function registerSingletons(): void
    {
        $this->app->registerSingleton('toolkit-adapters', fn (FrameworkCore $app) => new ToolkitAdapterManager($app));
        $this->app->alias('toolkit-adapters', ToolkitAdapterManager::class);

        $this->app->registerSingleton('toolkit-windows', fn (FrameworkCore $app) => new ToolkitWindowManager(
            app('toolkit-adapters'),
        ));

        $this->app->registerSingleton('toolkit-menus', fn (FrameworkCore $app) => new MenuManager(
            config('toolkits.windows', []),
            $app,
        ));
        $this->app->alias('toolkit-menus', MenuManager::class);
    }

    protected function provisionEventLoop(): void
    {
        $this->callAfterResolving( 'event-loop', function (LoopInterface $loop): void {
            if ($this->app->isApplicationRunning()) {

                $this->addToolkitsToLoop($loop);
                $this->registerContentManager();
                $this->registerNavigation();
                $this->loadRoutes();
            }
        });
    }

    /**
     * @throws ReflectionException
     */
    protected function addToolkitsToLoop(LoopInterface $loop): void
    {
        $toolkit_adapters = ToolkitAdapters::adapters();
        foreach($toolkit_adapters as $toolkit_adapter)
        {
            $adapter = $toolkit_adapter instanceof ToolkitAdapterDriver ? $toolkit_adapter->session() : null;

            if ($adapter?->connected()) {
                $adapter->joinLoop($loop);
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function registerContentManager(): void
    {
        $this->app->registerSingleton('content', fn (FrameworkCore $app) => new PresentationTreeManager($app));
        $this->app->alias('content', PresentationTreeManager::class);
    }

    /**
     * A navigator belongs to the window that shows routes, not to the application: asking
     * the container for one gives that window's, so a controller or service reaches the same
     * history the window's views do. A component asks its own window: $this->navigator().
     */
    protected function registerNavigation(): void
    {
        $this->app->bind(Navigator::class, static fn () => Presentation::driver()->navigator());
    }

    protected function loadRoutes(): void
    {
        // The component's routes first, then the application's: a route of the same name replaces it.
        require_once __DIR__ . "/routes/navigation.php";

        $app_routes = $this->app->basePath('routes/navigation.php');
        if (is_file($app_routes)) {
            require_once $app_routes;
        }
    }
}