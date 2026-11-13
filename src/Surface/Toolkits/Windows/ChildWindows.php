<?php

namespace Surface\Toolkits\Windows;

use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Contracts\Toolkits\Menus\MenuProfile;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\Drivers\ToolkitWindowDriver;

class ChildWindows
{
    /**
     * @throws ToolkitRuntimeException When the parent is closed, is not a window of $driver, is a child,
     *                         or has an open child, or $menu is not null.
     */
    public static function check(ToolkitWindowDriver $driver, ToolkitWindow $parent, ?MenuProfile $menu): void
    {
        $name = $parent->name();
        if (! $parent->isOpen()) {
            throw new ToolkitRuntimeException("Window '{$name}' is closed, so it cannot be a parent.");
        }
        if ($driver->get($name) !== $parent) {
            throw new ToolkitRuntimeException("Window '{$name}' is not a window of this driver, so it cannot be a parent.");
        }
        if (! is_null($parent->parent())) {
            throw new ToolkitRuntimeException("Window '{$name}' is a child, so it cannot be a parent.");
        }
        $child = self::childOf($driver, $parent);
        if (! is_null($child)) {
            throw new ToolkitRuntimeException("Window '{$name}' already has the open child '{$child->name()}'.");
        }
        if (! is_null($menu)) {
            throw new ToolkitRuntimeException("A child of window '{$name}' cannot have a menu bar.");
        }
    }

    /** @throws ToolkitRuntimeException When $window is a child: a child has no menu bar. */
    public static function refuseMenuBar(ToolkitWindow $window): void
    {
        if (! is_null($window->parent())) {
            throw new ToolkitRuntimeException("Window '{$window->name()}' is a child, so it cannot have a menu bar.");
        }
    }

    /** The open window of $driver whose parent is $parent, or null. */
    public static function childOf(ToolkitWindowDriver $driver, ToolkitWindow $parent): ?ToolkitWindow
    {
        foreach ($driver->all() as $window) {
            if ($window->parent() === $parent && $window->isOpen()) {
                return $window;
            }
        }

        return null;
    }
}