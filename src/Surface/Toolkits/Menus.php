<?php

namespace Surface\Toolkits;

use Closure;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\SharedBar;
use Surface\Toolkits\Menus\MenuException;
use Surface\Toolkits\Managers\MenuManager;

/**
 * The menus of an application.
 *
 *     ToolkitWindows::open('tools', 400, 300, menu: Menus::sharedWith('main'))
 *
 *     // in a controller
 *     Menus::page(fn (MenuBar $bar) => $bar->extend('file', fn ($file) => $file->append(...)));
 *     Menus::on('file.save', fn (MenuMail $mail) => ...);
 */
class Menus
{
    /**
     * The bar of a profile in config/toolkits.php. A value: change it with its own methods
     * and the profile stays as it was.
     *
     * @throws MenuException When there is no such profile, or it cannot be one.
     */
    public static function profile(string $name): MenuBar
    {
        return self::manager()->profile($name);
    }

    /**
     * An open window's own bar: a profile's name, a bar, another window's, or null for the
     * default profile.
     */
    public static function window(string $name, string|MenuBar|SharedBar|null $menu): void
    {
        self::manager()->window($name, $menu);
    }

    /**
     * In a controller: this page's bar. A profile's name or a bar replaces the one it was
     * given; a closure is given it and returns the one to show.
     *
     * @param string|MenuBar|Closure(MenuBar): MenuBar $menu
     */
    public static function page(string|MenuBar|Closure $menu): void
    {
        self::manager()->page($menu);
    }

    /**
     * The bar $window shows, followed live; its items run that window's handlers.
     */
    public static function sharedWith(string $window): SharedBar
    {
        return new SharedBar($window);
    }

    /**
     * In a controller: run $listener on menu mail with this path, until the window opens
     * another route.
     *
     * @param Closure(\Surface\Contracts\Toolkits\Mail\Menus\MenuMail): mixed $listener
     */
    public static function on(string $path, Closure $listener): void
    {
        self::manager()->on($path, $listener);
    }

    public static function manager(): MenuManager
    {
        return app('toolkit-menus');
    }
}
