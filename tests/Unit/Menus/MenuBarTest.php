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

function appBar(): MenuBar
{
    return MenuBar::make(
        Menu::application(
            About::panel(name: 'My App', version: '11.0'),
            Separator::make(),
            Standard::quit(),
        ),
        Menu::make('File', 'file',
            Item::make('Open', id: 'open')->shortcut(Shortcut::primary('O')),
            Item::make('Save', id: 'save')->shortcut(Shortcut::primary('S')),
            Menu::make('Export', 'export', Item::make('PDF', id: 'pdf')),
        ),
        Standard::editMenu(),
    );
}

function keys(MenuBar $bar): array
{
    return array_map(fn (Menu $menu) => $menu->key(), $bar->menus);
}

it('holds menus in order', function (): void {
    expect(keys(appBar()))->toBe(['app', 'file', 'edit'])
        ->and(appBar()->application()?->application)->toBeTrue()
        ->and(MenuBar::make()->menus)->toBe([])
        ->and(MenuBar::make()->application())->toBeNull();
});

it('refuses what a bar cannot be', function (): void {
    expect(fn () => new MenuBar([Item::make('Save', id: 'save')]))
        ->toThrow(MenuException::class, 'A menu bar holds menus, not Surface\Toolkits\Menus\Item: put it in a Menu.')
        ->and(fn () => MenuBar::make(Menu::application(), Menu::application()))
        ->toThrow(MenuException::class, 'A menu bar has one application menu, not two.')
        ->and(fn () => MenuBar::make(Menu::make('File'), Menu::make('File')))
        ->toThrow(MenuException::class, "Two menus of the bar have the key 'File'. Give one of them another id.")
        ->and(fn () => MenuBar::make(Menu::application(), Menu::make('Apps', 'app')))
        ->toThrow(MenuException::class, "Two menus of the bar have the key 'app'. Give one of them another id.");
});

it('names each item by the ids of the menus down to it', function (): void {
    $bar = appBar();

    expect(array_keys($bar->paths()))->toBe(['file.open', 'file.save', 'file.export.pdf'])
        ->and($bar->find('file.export.pdf')->label)->toBe('PDF')
        ->and($bar->find('file.pdf'))->toBeNull();
});

it('lets two menus use one item id, each with its own path', function (): void {
    $bar = MenuBar::make(
        Menu::make('File', 'file', Item::make('New file', id: 'new')),
        Menu::make('Tab', 'tab', Item::make('New tab', id: 'new'), Menu::make('More', 'more', Item::make('New group', id: 'new'))),
    );

    expect(array_keys($bar->paths()))->toBe(['file.new', 'tab.new', 'tab.more.new'])
        ->and($bar->item('tab.new', fn (Item $new) => $new->enabled(false))->find('file.new')->enabled)->toBeTrue()
        ->and($bar->item('tab.more.new', fn (Item $new) => $new->enabled(false))->find('tab.new')->enabled)->toBeTrue();
});

it('adds a menu at the end, or in place of the one with its key', function (): void {
    $bar = appBar();
    $format = Menu::make('Format', 'format', Toggle::make('Wrap', on: false, id: 'wrap'));
    $file = Menu::make('Datei', 'file', Item::make('New', id: 'new'));

    expect(keys($bar->with($format)))->toBe(['app', 'file', 'edit', 'format'])
        ->and(keys($bar->with($file, $format)))->toBe(['app', 'file', 'edit', 'format'])
        ->and($bar->with($file)->menu('file')->title)->toBe('Datei')
        ->and($bar->with(Menu::application(Standard::quit()))->application()->nodes)->toHaveCount(1);
});

it('finds a menu with no id by its title', function (): void {
    $bar = MenuBar::make(Menu::make('Help', Separator::make()));

    expect($bar->menu('Help')->title)->toBe('Help')
        ->and($bar->without('Help')->menus)->toBe([]);
});

it('places, changes and removes menus by key', function (): void {
    $bar = appBar();
    $view = Menu::make('View', 'view', Toggle::make('Sidebar', on: true, id: 'sidebar'));

    expect(keys($bar->before('edit', $view)))->toBe(['app', 'file', 'view', 'edit'])
        ->and(keys($bar->after('app', $view)))->toBe(['app', 'view', 'file', 'edit'])
        ->and(keys($bar->without('edit')))->toBe(['app', 'file'])
        ->and($bar->extend('file', fn (Menu $file) => $file->append(Item::make('Print', id: 'print')))->find('file.print'))
        ->toBeInstanceOf(Item::class)
        ->and($bar->menu('help'))->toBeNull();
});

it('ignores removing a menu that is not there', function (): void {
    expect(appBar()->without('help'))->toEqual(appBar())
        ->and(keys(appBar()->without('help', 'edit')))->toBe(['app', 'file']);
});

it('refuses to change or place beside what is not there', function (): void {
    $bar = appBar();
    $help = Menu::make('Help', 'help');
    $missing = "The menu bar has no menu with the key 'help'. It has: app, file, edit.";

    expect(fn () => $bar->extend('help', fn (Menu $menu) => $menu))->toThrow(MenuException::class, $missing)
        ->and(fn () => $bar->before('help', $help))->toThrow(MenuException::class, $missing)
        ->and(fn () => $bar->after('help', $help))->toThrow(MenuException::class, $missing)
        ->and(fn () => $bar->item('file.print', fn ($item) => $item))
        ->toThrow(MenuException::class, "The menu bar has nothing at 'file.print'. It has: file.open, file.save, file.export.pdf.");
});

it('changes one item by its path', function (): void {
    $changed = appBar()
        ->item('file.save', fn (Item $save) => $save->enabled(false))
        ->item('file.export.pdf', fn (Item $pdf) => $pdf->label('PDF...'));

    expect($changed->find('file.save')->enabled)->toBeFalse()
        ->and($changed->find('file.export.pdf')->label)->toBe('PDF...')
        ->and($changed->find('file.open')->enabled)->toBeTrue();
});

it('lets a page take over an inherited toggle or choice, in its place', function (): void {
    $profile = MenuBar::make(Menu::make('View', 'view',
        Toggle::make('Wrap', on: true, id: 'wrap')->shortcut(Shortcut::primary('W')),
        Choice::make(['a' => 'A', 'b' => 'B'], 'a', id: 'mode'),
    ));
    $run = fn () => null;
    $page = $profile
        ->item('view.wrap', fn (Toggle $wrap) => $wrap->onToggle($run))
        ->item('view.mode', fn (Choice $mode) => $mode->onChoose($run));

    expect($page->find('view.wrap')->callback())->toBe($run)
        ->and($page->find('view.wrap')->shortcut->describe())->toBe('Primary+W')
        ->and($page->find('view.mode')->callback())->toBe($run)
        ->and(array_keys($page->paths()))->toBe(['view.wrap', 'view.mode'])
        ->and($profile->callbacks())->toBe([]);
});

it('lists the nodes that run callbacks', function (): void {
    $bar = appBar()->with(Menu::make('View', Toggle::make('Sidebar', on: true, onToggle: fn (bool $on) => null)));

    expect(appBar()->callbacks())->toBe([])
        ->and(array_map(fn ($node) => $node->describe(), $bar->callbacks()))->toBe(["toggle 'Sidebar'"]);
});

it('leaves out only what another platform does not have', function (): void {
    $bar = MenuBar::make(
        Menu::application(Standard::hide(), Standard::hideOthers(), Standard::showAll(), Standard::quit()),
        Menu::make('More', Menu::make('Inner', Standard::hide()->shortcut(null))),
    );

    expect(array_map(fn ($node) => $node->key(), $bar->on(true)->all()))->toBe(['hide', 'hide-others', 'show-all', 'quit', 'Inner', 'hide'])
        ->and(array_map(fn ($node) => $node->key(), $bar->on(false)->all()))->toBe(['quit', 'Inner']);
});

it('refuses two items with one shortcut on the platform that shows both', function (): void {
    $quit = MenuBar::make(Menu::make('File', 'file',
        Item::make('Quick Open', id: 'quick')->shortcut(Shortcut::primary('Q')),
        Standard::quit(),
    ));
    $control = MenuBar::make(Menu::make('File', 'file',
        Item::make('Save', id: 'save')->shortcut(Shortcut::primary('S')),
        Item::make('Sync', id: 'sync')->shortcut(Shortcut::of('Ctrl+S')),
    ));

    expect(fn () => $quit->on(true))
        ->toThrow(MenuException::class, "Item 'Quick Open' and standard item 'quit' have the same shortcut, Cmd+Q.")
        ->and(fn () => $quit->on(false))
        ->toThrow(MenuException::class, "Item 'Quick Open' and standard item 'quit' have the same shortcut, Ctrl+Q.")
        ->and(fn () => $control->on(false))
        ->toThrow(MenuException::class, "Item 'Save' and item 'Sync' have the same shortcut, Ctrl+S.")
        ->and($control->on(true)->all())->toHaveCount(2);
});

it('comes back from a cached config file as it was written', function (): void {
    $bar = appBar()->with(Menu::make('View', 'view',
        Toggle::make('Sidebar', on: true, id: 'sidebar')->shortcut(Shortcut::of('Primary+Alt+S')),
        Choice::make(['fit' => 'Fit', '100' => 'Actual Size'], 'fit', id: 'zoom'),
        Standard::fullscreen()->label('Big')->shortcut(null),
        Standard::preferences(id: 'settings'),
    ));

    $file = tempnam(sys_get_temp_dir(), 'topside-menus');
    file_put_contents($file, '<?php return '.var_export(['windows' => ['menus' => ['app' => $bar]]], true).';');
    try {
        $read = (require $file)['windows']['menus']['app'];
    } finally {
        unlink($file);
    }

    expect($read)->toEqual($bar)
        ->and($read)->not->toBe($bar)
        ->and(array_keys($read->paths()))->toBe(array_keys($bar->paths()));
});

it('cannot be cached while it holds a callback', function (): void {
    $bar = MenuBar::make(Menu::make('File', Item::make('Save', onClick: fn () => null)));

    expect(fn () => eval('return '.var_export($bar, true).';'))->toThrow(Error::class);
});
