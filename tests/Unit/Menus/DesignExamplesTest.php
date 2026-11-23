<?php

declare(strict_types=1);

use Surface\Toolkits\Menus\About;
use Surface\Toolkits\Menus\Choice;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\MenuException;
use Surface\Toolkits\Menus\Separator;
use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\Standard;
use Surface\Toolkits\Menus\Toggle;

/*
| The examples of .okf/design/menus.md that need no runtime.
*/

it('builds the File menu', function (): void {
    $file = Menu::make('File', 'file',                  // title, then the id
        Item::make('Save', id: 'save')->shortcut(Shortcut::primary('S')),
        Separator::make(),
        Standard::closeWindow(),
    );

    expect(array_keys(MenuBar::make($file)->on(true)->paths()))->toBe(['file.save']);
});

it('builds the changed profile', function (): void {
    $bar = MenuBar::make(Menu::make('File', 'file'), Menu::make('Help', 'help'))
        ->extend('file', fn (Menu $file) => $file->append(Item::make('Export PDF', onClick: fn () => null)))
        ->with(Menu::make('Format', Separator::make()))
        ->without('help');

    expect(array_map(fn (Menu $menu) => $menu->key(), $bar->menus))->toBe(['file', 'Format']);
});

it('builds the application menu', function (): void {
    $bar = MenuBar::make(
        Menu::application(
            About::panel(name: 'My App', version: '11.0'),
            Separator::make(),
            Standard::quit(),
        ),
        Menu::make('File', 'file'),
    );

    expect($bar->on(true)->application()->id)->toBe('app');
});

it('builds what the component returns', function (): void {
    $bar = MenuBar::make(Menu::make('File', 'file', Item::make('Save', id: 'save')))
        ->item('file.save', fn (Item $save) => $save->enabled(false))
        ->with(Menu::make('Format',
            Toggle::make('Word Wrap', on: true, onToggle: fn (bool $on) => null),
        ));

    expect($bar->find('file.save')->enabled)->toBeFalse()
        ->and($bar->menu('Format')->nodes)->toHaveCount(1);
});

it('builds what the Sharp block describes', function (): void {
    $bar = MenuBar::make(Menu::make('File', 'file'))
        ->extend('file', fn (Menu $file) => $file->append(
            Item::make('Save', onClick: fn () => null)->enabled(false)->shortcut(Shortcut::of('Primary+S')),
        ))
        ->with(Menu::make('Format', 'format',
            Toggle::make('Word Wrap', on: true, onToggle: fn (bool $on) => null),
            Separator::make(),
            Item::make('Export PDF', id: 'export'),
        ));

    expect(array_keys($bar->on(true)->paths()))->toBe(['format.export'])
        ->and($bar->on(false)->menus)->toHaveCount(2);
});
