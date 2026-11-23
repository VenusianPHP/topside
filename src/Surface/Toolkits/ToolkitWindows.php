<?php

namespace Surface\Toolkits;

use ReflectionException;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\SharedBar;
use Surface\Toolkits\Managers\ToolkitWindowManager;

class ToolkitWindows
{
    /**
     * @param string|MenuBar|SharedBar|null $menu the window's own bar: a profile's name, a
     *        bar, Menus::sharedWith() another window, or null for the default profile
     * @throws ReflectionException
     */
    public static function open(string $name, int $width, int $height, string|MenuBar|SharedBar|null $menu = null, $parent = null): ToolkitPoweredWindow
    {
        /** @var  ToolkitWindowManager $window_mgr */
        $window_mgr = app('toolkit-windows');

        return $window_mgr->open($name, $width, $height, $menu, $parent);
    }
}