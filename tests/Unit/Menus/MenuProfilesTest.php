<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\Mail\Menus\MenuItemClicked;
use Surface\Contracts\Toolkits\Mail\Menus\MenuItemToggled;
use Surface\Contracts\Toolkits\Mail\Menus\MenuMail;
use Surface\Contracts\Toolkits\Mail\Menus\MenuOptionChosen;
use Surface\Toolkits\Managers\MenuManager;
use Surface\Toolkits\Menus;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\MenuException;
use Venusian\Topside\Tests\Fakes\Menus\FakeMenus;
use Voyager\Contracts\Signals\NamedSignal;

function withProfiles(array $menus, ?string $default = null): void
{
    topside(['toolkits' => ['windows' => ['default_menu' => $default, 'menus' => $menus]]]);
}

it('ships no menu of its own', function (): void {
    expect(config('toolkits.windows'))->toBe(['default_menu' => null, 'menus' => []])
        ->and(config('toolkits.menus'))->toBeNull()
        ->and(Menus::manager()->names())->toBe([])
        ->and(Menus::manager()->default())->toBeNull()
        ->and(app(MenuManager::class))->toBe(app('toolkit-menus'))
        ->and(fn () => Menus::profile('app'))->toThrow(MenuException::class, "No menu profile named 'app'. None is defined.");
});

it('reads a profile and the default from the config', function (): void {
    $bar = MenuBar::make(Menu::make('File', 'file', Item::make('Open', id: 'open')));
    withProfiles(['app' => $bar], 'app');

    expect(Menus::profile('app'))->toBe($bar)
        ->and(Menus::manager()->default())->toBe($bar)
        ->and(fn () => Menus::profile('editor'))->toThrow(MenuException::class, "No menu profile named 'editor'. Defined: app.");
});

it('reads what a method called in the config returned', function (): void {
    withProfiles(['editor' => FakeMenus::bar()]);

    expect(array_keys(Menus::profile('editor')->paths()))->toBe(['file.open']);
});

it('allows a windows key with only menus, as an app may publish', function (): void {
    expect((new MenuManager(['menus' => ['app' => MenuBar::make()]]))->default())->toBeNull();
});

it('takes only a MenuBar as a profile', function (mixed $profile, string $type): void {
    $manager = new MenuManager(['menus' => ['app' => $profile]]);

    expect(fn () => $manager->profile('app'))
        ->toThrow(MenuException::class, "Menu profile 'app' must be a MenuBar, not {$type}. For a method, call it in the config file: EditorMenus::bar().");
})->with([
    'a name' => ['app', 'string'],
    'arrays' => [[['label' => 'App']], 'array'],
    'a method array' => [[FakeMenus::class, 'bar'], 'array'],
    'a closure' => [fn () => MenuBar::make(), 'Closure'],
]);

it('refuses a callback in a profile, naming both', function (): void {
    withProfiles(['app' => MenuBar::make(Menu::make('File', Item::make('Save', onClick: fn () => null)))]);

    expect(fn () => Menus::profile('app'))
        ->toThrow(MenuException::class, "Menu profile 'app' is in the config file, so it cannot run callbacks: item 'Save' has one. Give it an id and listen for its mail.");
});

it('gives a value the profile is not changed by', function (): void {
    withProfiles(['app' => FakeMenus::bar()]);

    $changed = Menus::profile('app')->extend('file', fn (Menu $file) => $file->append(Item::make('Save', id: 'save')));

    expect(array_keys($changed->paths()))->toBe(['file.open', 'file.save'])
        ->and(array_keys(Menus::profile('app')->paths()))->toBe(['file.open']);
});

it('names menu mail by what happened and the path', function (): void {
    $clicked = new MenuItemClicked('main', 'file.export.pdf');
    $toggled = new MenuItemToggled(null, 'view.wrap', true);
    $chosen = new MenuOptionChosen('main', 'view.zoom', 'fit');

    expect($clicked->name())->toBe('menu.clicked.file.export.pdf')
        ->and($clicked->window())->toBe('main')
        ->and($clicked->path())->toBe('file.export.pdf')
        ->and($toggled->name())->toBe('menu.toggled.view.wrap')
        ->and($toggled->window())->toBeNull()
        ->and($toggled->on)->toBeTrue()
        ->and($chosen->name())->toBe('menu.chosen.view.zoom')
        ->and($chosen->option)->toBe('fit')
        ->and($clicked)->toBeInstanceOf(MenuMail::class)
        ->and($clicked)->toBeInstanceOf(NamedSignal::class);
});

it('can be heard by name or by a wildcard, and by no other menu', function (): void {
    $heard = [];
    foreach (['menu.clicked.format.export', 'menu.clicked.format.*', 'menu.clicked.file.*'] as $name) {
        $this->app['signals']->listen($name, function () use (&$heard, $name): void {
            $heard[] = $name;
        });
    }

    $this->app['signals']->dispatch(new MenuItemClicked('main', 'format.export'));

    expect($heard)->toEqualCanonicalizing(['menu.clicked.format.export', 'menu.clicked.format.*']);
});
