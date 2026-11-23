<?php

namespace Surface\Toolkits\Managers;

use ReflectionException;
use RuntimeException;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\SharedBar;
use Surface\Contracts\Toolkits\ToolkitAdapterDriver;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;

class ToolkitWindowManager
{
    public function __construct(
        protected readonly ToolkitAdapterManager $adapters_mgr,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function open(string $name, int $width, int $height, string|MenuBar|SharedBar|null $menu = null, $parent = null): ToolkitPoweredWindow
    {
        $driver = $this->adapter();
        $window = $driver->open(
            $name,
            $width,
            $height,
            $parent
        );
        app(MenuManager::class)->opened($window, $driver, $menu);

        return $window;
    }

    /**
     * @throws ReflectionException
     */
    protected function adapter(): ToolkitAdapterDriver
    {
        $tk_driver = config('toolkits.default', 'null');
        $driver = $this->adapters_mgr->driver($tk_driver);

        return $driver instanceof ToolkitAdapterDriver
            ? $driver
            : throw new RuntimeException(get_class($driver).' does not open windows.');
    }
}