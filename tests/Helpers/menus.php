<?php

declare(strict_types=1);

use Surface\Toolkits\Presentation;
use Surface\Toolkits\ToolkitAdapters;
use Surface\Toolkits\Managers\MenuManager;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\Shortcut;
use Surface\Core\Actions\Bootstrap\LoadSignals;
use Voyager\Contracts\Debug\ExceptionHandler;
use Venusian\Topside\Tests\Fakes\FakeExceptionHandler;
use Venusian\Topside\Tests\Fakes\Menus\FakeMenuBar;
use Venusian\Topside\Tests\Fakes\Views\MenuView;

/*
| Helpers for menu runtime tests. They run the path an application does: the shell opens the
| window, a native bar's signal posts mail to the loop, and the signals deliver it through
| what LoadSignals wired.
*/

/** An application with a window open, its signals wired and its platform chosen. */
function openMenus(array $profiles = [], ?string $default = null, bool $mac = false): void
{
    $app = test()->app;
    test()->reports = new FakeExceptionHandler();
    $app->registerInstance(ExceptionHandler::class, test()->reports);

    $manager = new MenuManager(['menus' => $profiles, 'default_menu' => $default], $app, $mac);
    $app->registerInstance('toolkit-menus', $manager);

    FakeMenuBar::$unsupported = [];
    FakeMenuBar::$shown = [];
    MenuView::$menus = null;
    MenuView::$onButton = null;

    (new LoadSignals())->bootstrap($app);
    openViewWindow();
}

/** The File menu most tests start from. */
function fileBar(): MenuBar
{
    return MenuBar::make(Menu::make('File', 'file',
        Item::make('Open', id: 'open'),
        Item::make('Save', id: 'save')->shortcut(Shortcut::primary('S')),
    ));
}

/** A window's native bar; the presentation window's when none is named, '' for no window's. */
function nativeBar(?string $window = null): FakeMenuBar
{
    return ToolkitAdapters::driver()->bars[$window ?? Presentation::driver()->window()->name()];
}

/** Every turn of the loop until nothing waits: what was posted, in order. */
function turnLoop(): array
{
    $seen = [];
    while (test()->loop->posted !== []) {
        [$posted, test()->loop->posted] = [test()->loop->posted, []];
        foreach ($posted as $mail) {
            $seen[] = $mail;
            test()->app['signals']->dispatch($mail);
        }
    }

    return $seen;
}

/** @return list<string> the names of the public mail in $posted */
function publicMail(array $posted): array
{
    return array_values(array_filter(
        array_map(static fn (object $mail): string => $mail->name(), $posted),
        static fn (string $name): bool => $name !== 'menu.activated',
    ));
}

/** @return list<string> the names of what is in the window's content column */
function childNamesOfScreen(): array
{
    return array_map(fn ($child) => $child->name(), screen()->children());
}
