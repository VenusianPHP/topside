<?php

namespace Venusian\Topside\Tests\Fakes\Menus;

use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\Standard;

/**
 * Menus from methods, as an application writes them: pieces that are put together.
 */
class FakeMenus
{
    public static function bar(): MenuBar
    {
        return MenuBar::make(self::file(), Standard::editMenu());
    }

    public static function file(): Menu
    {
        return Menu::make('File', 'file', Item::make('Open', id: 'open'));
    }
}
