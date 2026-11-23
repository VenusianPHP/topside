<?php

return [
    'default' => env('TOOLKIT_DRIVER', 'null'),

    /*
    |----------------------------------------------------------------------
    | Windows
    |----------------------------------------------------------------------
    |
    | 'menus' holds menu bars by name: each is a MenuBar, written here or
    | returned by a method you call here. 'default_menu' names the one a
    | window shows when nothing gives it another; null for none.
    |
    | Topside defines no menu of its own. A menu written here posts mail
    | when an item with an id is chosen (menu.clicked.file.open below);
    | it cannot hold a callback, because a closure cannot be cached.
    |
    |   use Surface\Toolkits\Menus\{About, Item, Menu, MenuBar, Separator, Standard};
    |
    |   'default_menu' => 'app',
    |   'menus' => [
    |       'app' => MenuBar::make(
    |           Menu::application(
    |               About::panel(name: 'My App', version: '1.0'),
    |               Separator::make(),
    |               Standard::quit(),
    |           ),
    |           Menu::make('File', 'file', Item::make('Open', id: 'open')),
    |           Standard::editMenu(),
    |       ),
    |       'editor' => EditorMenus::bar(),
    |   ],
    |
    */
    'windows' => [
        'default_menu' => null,
        'menus' => [],
    ],
];
