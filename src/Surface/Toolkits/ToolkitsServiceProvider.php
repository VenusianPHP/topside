<?php

namespace Surface\Toolkits;

use ReflectionException;
use Surface\Contracts\Toolkits\ToolkitBridge;
use Surface\Toolkits\Managers\ContentTreeManager;
use Surface\Toolkits\Managers\ToolkitBridgeManager;
use Surface\Toolkits\Managers\ToolkitWindowManager;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Toolkits\Support\Live;
use Surface\Toolkits\Support\ViewActions;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Contracts\IOPools\Loop as LoopInterface;
use Voyager\NutsAndBolts\ServiceProvider;

class ToolkitsServiceProvider extends ServiceProvider
{
    /**
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->app->registerSingleton('toolkit-bridge-manager', fn (FrameworkCore $app) => new ToolkitBridgeManager($app));
        $this->app->alias('toolkit-bridge-manager', ToolkitBridgeManager::class);

        $this->app->registerSingleton('toolkit-windows', fn (FrameworkCore $app) => new ToolkitWindowManager(
            $app->get('toolkit-bridge-manager'),
            config('windows.menus', []),
            config('windows.default_menu'),
        ));
        $this->app->alias('toolkit-windows', ToolkitWindowManager::class);

        // What a widget does when the user acts on it, and the built views' values that follow app state.
        $this->app->registerSingleton(ViewActions::class, fn () => new ViewActions());
        $this->app->registerSingleton(Live::class, fn () => new Live());

        $this->provisionEventLoop();
    }

    /**
     * @throws ReflectionException
     */
    protected function provisionEventLoop(): void
    {
        $this->callAfterResolving( 'event-loop', function (LoopInterface $loop): void {
            if (! $this->app->isApplicationRunning()) {
                return;
            }

            foreach(Toolkits::bridges() as $bridge_driver)
            {
                $session = $bridge_driver instanceof ToolkitBridge ? $bridge_driver->session() : null;
                if ($session?->connected()) {
                    $session->joinLoop($loop);
                }
            }

            $this->registerContentManager();
            $this->registerNavigation();
            $this->loadMainWindowRoutes();
        });
    }

    /**
     * One ContentManager for the app's life. The manager caches each driver it
     * creates, so the toolkit and silk tree drivers are singletons too.
     * @throws ReflectionException
     */
    private function registerContentManager(): void
    {
        $this->app->registerSingleton('content', fn (FrameworkCore $app) => new ContentTreeManager($app));
        $this->app->alias('content', ContentTreeManager::class);
    }

    /**
     * @throws ReflectionException
     */
    private function registerNavigation(): void
    {
        $this->app->registerSingleton('navigation', fn (FrameworkCore $app) => new Navigator());
        $this->app->alias('navigation', Navigator::class);
    }

    private function loadMainWindowRoutes(): void
    {
        // The component's routes first, then the application's: a route of the same name replaces it.
        require_once __DIR__ . "/routes/navigation.php";

        $app_routes = $this->app->basePath('routes/navigation.php');
        if (is_file($app_routes)) {
            require_once $app_routes;
        }
    }
}