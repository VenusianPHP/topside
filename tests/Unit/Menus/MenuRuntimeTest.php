<?php

declare(strict_types=1);

use Surface\Toolkits\View;
use Surface\Toolkits\Menus;
use Surface\Toolkits\Presentation;
use Surface\Toolkits\ToolkitAdapters;
use Surface\Toolkits\ToolkitWindows;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Toolkits\Navigation\ToolkitPageRoute as Route;
use Surface\Toolkits\Menus\About;
use Surface\Toolkits\Menus\Choice;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\MenuException;
use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\Standard;
use Surface\Toolkits\Menus\Toggle;
use Surface\Contracts\Toolkits\Menus\StandardKind;
use Surface\Contracts\Toolkits\Mail\QuitRequested;
use Surface\Contracts\Toolkits\Mail\Menus\MenuMail;
use Surface\Contracts\Toolkits\Mail\Menus\MenuItemClicked;
use Venusian\Topside\Tests\Fakes\Menus\FakeMenuBar;
use Venusian\Topside\Tests\Fakes\Views\MenuView;

/** A controller whose action each test writes. */
class MenuTestController
{
    public static ?Closure $action = null;

    public function show(): void
    {
        is_null(static::$action) || (static::$action)();
    }
}

function goPage(?Closure $action = null, string|MenuBar|Closure|null $menu = null, string $name = '/menus-page'): void
{
    MenuTestController::$action = $action;
    $route = Route::get($name, [MenuTestController::class, 'show']);
    if (! is_null($menu)) {
        $route->menu($menu);
    }
    app(Navigator::class)->go($name);
}

function tag(string $id): Menu
{
    return Menu::make(strtoupper($id), $id);
}

function appWindow(): string
{
    return Presentation::driver()->window()->name();
}

function menuView(): MenuView
{
    return Presentation::driver()->view()->component;
}

/*
|----------------------------------------------------------------------
| The four steps
|----------------------------------------------------------------------
*/

it('gives each step the bar from the one before', function (): void {
    openMenus(['app' => MenuBar::make(tag('window'))], 'app');
    $seen = [];
    $titles = fn (MenuBar $bar) => implode(',', array_map(fn (Menu $m) => $m->key(), $bar->menus));
    MenuView::$menus = function ($view, MenuBar $bar) use (&$seen, $titles) {
        $seen['view'] = $titles($bar);

        return $bar->with(tag('view'));
    };

    goPage(
        function () use (&$seen, $titles): void {
            Menus::page(function (MenuBar $bar) use (&$seen, $titles) {
                $seen['page'] = $titles($bar);

                return $bar->with(tag('page'));
            });
            View::render(MenuView::class);
        },
        function (MenuBar $bar) use (&$seen, $titles) {
            $seen['route'] = $titles($bar);

            return $bar->with(tag('route'));
        },
    );

    expect($seen)->toEqualCanonicalizing(['route' => 'window', 'page' => 'window,route', 'view' => 'window,route,page'])
        ->and(nativeBar()->titles())->toBe(['WINDOW', 'ROUTE', 'PAGE', 'VIEW'])
        ->and($this->reports->messages())->toBe([]);
});

it('lets the last step that says anything win', function (): void {
    openMenus(['app' => MenuBar::make(tag('window')), 'route' => MenuBar::make(tag('route')), 'page' => MenuBar::make(tag('page'))], 'app');

    goPage(menu: 'route');
    expect(nativeBar()->titles())->toBe(['ROUTE']);

    goPage(fn () => Menus::page('page'), 'route');
    expect(nativeBar()->titles())->toBe(['PAGE']);

    MenuView::$menus = fn () => MenuBar::make(tag('view'));
    goPage(function (): void {
        Menus::page('page');
        View::render(MenuView::class);
    }, 'route');
    expect(nativeBar()->titles())->toBe(['VIEW']);
});

it('takes a bar or a closure wherever it takes a name', function (): void {
    openMenus();

    goPage(menu: MenuBar::make(tag('route')));
    expect(nativeBar()->titles())->toBe(['ROUTE']);

    goPage(fn () => Menus::page(MenuBar::make(tag('page'))));
    expect(nativeBar()->titles())->toBe(['PAGE']);

    goPage(fn () => Menus::page(fn (MenuBar $bar) => $bar->with(tag('added'))));
    expect(nativeBar()->titles())->toBe(['ADDED']);
});

it('drops what the last route and its controller gave', function (): void {
    openMenus(['app' => MenuBar::make(tag('window'))], 'app');

    goPage(fn () => Menus::page(fn (MenuBar $bar) => $bar->with(tag('page'))), fn (MenuBar $bar) => $bar->with(tag('route')));
    expect(nativeBar()->titles())->toBe(['WINDOW', 'ROUTE', 'PAGE']);

    goPage(name: '/menus-other');
    expect(nativeBar()->titles())->toBe(['WINDOW']);
});

it('works the bar out once for a route, so nothing is built to be removed', function (): void {
    openMenus(['app' => fileBar()], 'app');
    MenuView::$menus = fn ($view, MenuBar $bar) => $bar->with(tag('format'));
    goPage(fn () => View::render(MenuView::class));
    nativeBar()->ops = [];

    goPage(fn () => View::render(MenuView::class), name: '/menus-same');

    expect(nativeBar()->ops)->toBe([]);
});

it('follows back and forward', function (): void {
    openMenus();
    Route::get('/one', [MenuTestController::class, 'show'])->menu(MenuBar::make(tag('one')));
    Route::get('/two', [MenuTestController::class, 'show'])->menu(MenuBar::make(tag('two')));
    MenuTestController::$action = null;
    $navigator = new Navigator();

    $navigator->go('/one');
    $navigator->go('/two');
    expect(nativeBar()->titles())->toBe(['TWO']);

    $navigator->back();
    expect(nativeBar()->titles())->toBe(['ONE']);

    $navigator->forward();
    expect(nativeBar()->titles())->toBe(['TWO']);
});

it('keeps a route an action list for find()', function (): void {
    Route::get('/found', [MenuTestController::class, 'show'])->menu('app');

    expect(Route::find('/found'))->toBe([MenuTestController::class, 'show'])
        ->and(Route::named('/found')->menuGiven())->toBe('app')
        ->and(fn () => Route::find('/none'))->toThrow(InvalidArgumentException::class, 'No route named [/none].');
});

it('gives a window with no menu the default, and none when there is none', function (): void {
    openMenus(['app' => fileBar()], 'app');
    expect(nativeBar()->titles())->toBe(['File']);


    ToolkitWindows::open('own', 300, 200, MenuBar::make(tag('own')));
    ToolkitWindows::open('plain', 300, 200);

    expect(nativeBar('own')->titles())->toBe(['OWN'])
        ->and(nativeBar('plain')->titles())->toBe(['File']);
});

it('shows no bar where nothing gave one', function (): void {
    openMenus(mac: true);

    expect(nativeBar()->titles())->toBe([]);

    goPage(fn () => View::render(MenuView::class));

    expect(nativeBar()->titles())->toBe([])
        ->and(nativeBar()->ops)->toBe([]);
});

it('builds the bar before the window is first shown', function (): void {
    openMenus(['app' => fileBar()], 'app');

    expect(Presentation::driver()->window()->presents)->toBe(0)
        ->and(nativeBar()->titles())->toBe(['File']);
});

it('replaces an open window\'s own bar', function (): void {
    openMenus(['app' => fileBar(), 'other' => MenuBar::make(tag('other'))], 'app');

    Menus::window(appWindow(), 'other');
    expect(nativeBar()->titles())->toBe(['OTHER']);

    Menus::window(appWindow(), null);
    expect(nativeBar()->titles())->toBe(['File']);
});

/*
|----------------------------------------------------------------------
| A view's menu
|----------------------------------------------------------------------
*/

it('asks the view again after a handler, and touches only what differs', function (): void {
    openMenus(['app' => fileBar()], 'app');
    MenuView::$menus = fn (MenuView $view, MenuBar $bar) => $bar->item('file.save', fn (Item $save) => $save->enabled($view->count > 0));
    View::render(MenuView::class);
    expect(nativeBar()->at('File/Save')->enabled)->toBeFalse();
    nativeBar()->ops = [];

    at(childNamesOfScreen()[0].'.press')->click();
    turnLoop();

    expect(nativeBar()->ops)->toBe([['change', 'Save', ['enabled' => true]]]);
});

/*
|----------------------------------------------------------------------
| What a click does
|----------------------------------------------------------------------
*/

it('runs a callback in a turn of the loop, then renders its view again', function (): void {
    openMenus();
    MenuView::$menus = fn (MenuView $view, MenuBar $bar) => $bar->with(Menu::make('Count',
        Item::make('Add', onClick: fn () => $view->count++),
        Item::make('Reset', onClick: fn () => $view->count = 0)->enabled($view->count > 0),
    ));
    View::render(MenuView::class);
    $view = menuView();

    nativeBar()->choose('Count/Add');
    expect($view->count)->toBe(0);    // never inside the toolkit's own callback

    $posted = turnLoop();

    expect($view->count)->toBe(1)
        ->and($view->renders)->toBe(2)
        ->and(nativeBar()->at('Count/Reset')->enabled)->toBeTrue()
        ->and(publicMail($posted))->toBe([]);
});

it('runs the callback, then posts the mail', function (): void {
    openMenus(['app' => fileBar()], 'app');
    $order = [];
    $this->app['signals']->listen('menu.clicked.file.save', function () use (&$order): void {
        $order[] = 'mail';
    });
    $run = function () use (&$order): void {
        $order[] = 'callback';
    };
    MenuView::$menus = fn ($view, MenuBar $bar) => $bar->item('file.save', fn (Item $save) => $save->onClick($run));
    View::render(MenuView::class);

    nativeBar()->choose('File/Save');
    turnLoop();

    expect($order)->toBe(['callback', 'mail']);
});

it('posts mail with the window that was focused', function (): void {
    openMenus(['app' => fileBar()], 'app', mac: true);
    ToolkitWindows::open('tools', 300, 200);
    $mails = [];
    $this->app['signals']->listen(MenuMail::class, function (MenuMail $mail) use (&$mails): void {
        $mails[] = [$mail->name(), $mail->window()];
    });

    nativeBar()->choose('File/Open');
    nativeBar('tools')->choose('File/Open');
    nativeBar('')->choose('File/Open');
    turnLoop();

    expect($mails)->toBe([
        ['menu.clicked.file.open', appWindow()],
        ['menu.clicked.file.open', 'tools'],
        ['menu.clicked.file.open', null],
    ]);
});

it('names mail by the whole path', function (): void {
    openMenus(['app' => MenuBar::make(Menu::make('File', 'file',
        Menu::make('Export', 'export', Item::make('PDF', id: 'pdf')),
        Toggle::make('Autosave', on: false, id: 'autosave'),
        Choice::make(['a' => 'A', 'b' => 'B'], 'a', id: 'mode'),
        Standard::copy()->id('copy'),
    ))], 'app');

    nativeBar()->choose('File/Export/PDF');
    nativeBar()->choose('File/Autosave');
    nativeBar()->choose('File/A|B', 'b');
    nativeBar()->choose('File/Copy');

    expect(publicMail(turnLoop()))->toBe([
        'menu.clicked.file.export.pdf', 'menu.toggled.file.autosave', 'menu.chosen.file.mode', 'menu.clicked.file.copy',
    ]);
});

it('posts nothing for what is disabled, done by the toolkit, unchanged or changed from code', function (): void {
    $bar = fn (bool $on) => MenuBar::make(Menu::make('M', 'm',
        Item::make('Off', id: 'off')->enabled(false),
        Standard::copy(),
        About::panel('Probe'),
        Toggle::make('Wrap', on: $on, id: 'wrap'),
        Choice::make(['a' => 'A', 'b' => 'B'], 'a', id: 'mode'),
    ));
    openMenus(['one' => $bar(false), 'two' => $bar(true)], 'one');

    nativeBar()->choose('M/Off');
    nativeBar()->choose('M/Copy');
    nativeBar()->choose('M/About topside-tests');
    nativeBar()->choose('M/Wrap', false);
    nativeBar()->choose('M/A|B', 'a');
    nativeBar()->choose('M/A|B', 'zzz');
    Menus::window(appWindow(), 'two');

    expect($this->loop->posted)->toBe([]);
});

it('drops a click on what is gone by the time it is delivered', function (): void {
    openMenus(['app' => fileBar(), 'other' => MenuBar::make(tag('other'))], 'app');

    nativeBar()->choose('File/Save');
    Menus::window(appWindow(), 'other');

    expect(publicMail(turnLoop()))->toBe([]);
});

it('posts quit.requested, unless the author replaced Quit', function (): void {
    openMenus(['app' => MenuBar::make(Menu::make('M', 'm', Standard::quit()))], 'app');

    nativeBar()->choose('M/Quit');
    $quit = array_values(array_filter(turnLoop(), fn ($mail) => $mail instanceof QuitRequested));
    expect($quit)->toHaveCount(1)
        ->and($quit[0]->window)->toBe(appWindow());

    Menus::window(appWindow(), MenuBar::make(Menu::make('M', 'm', Standard::quit()->id('leave'))));
    nativeBar()->choose('M/Quit');
    expect(publicMail(turnLoop()))->toBe(['menu.clicked.m.leave']);
});

it('runs a replaced standard item and the author\'s own About', function (): void {
    $ran = [];
    openMenus();
    Menus::window(appWindow(), MenuBar::make(Menu::make('M',
        Standard::copy()->onClick(function () use (&$ran): void { $ran[] = 'copy'; }),
        About::item(onClick: function () use (&$ran): void { $ran[] = 'about'; }),
        Standard::preferences(onClick: function () use (&$ran): void { $ran[] = 'preferences'; }),
    )));

    nativeBar()->choose('M/Copy');
    nativeBar()->choose('M/About topside-tests');
    nativeBar()->choose('M/Preferences');
    turnLoop();

    expect($ran)->toBe(['copy', 'about', 'preferences']);
});

/*
|----------------------------------------------------------------------
| Marks
|----------------------------------------------------------------------
*/

it('changes the mark at once, and hands the new value on later', function (): void {
    $got = [];
    openMenus();
    Menus::window(appWindow(), MenuBar::make(Menu::make('M',
        Toggle::make('Wrap', on: false, onToggle: function (bool $on) use (&$got): void { $got[] = $on; }),
        Choice::make(['1' => 'One', '2' => 'Two'], '1', onChoose: function (string $value) use (&$got): void { $got[] = $value; }),
    )));

    nativeBar()->choose('M/Wrap');
    nativeBar()->choose('M/One|Two', '2');

    expect(nativeBar()->at('M/Wrap')->on)->toBeTrue()
        ->and(nativeBar()->at('M/One|Two')->chosen)->toBe('2')
        ->and($got)->toBe([]);

    turnLoop();

    expect($got)->toBe([true, '2']);
});

it('stays in step with a view that keeps the state', function (): void {
    openMenus();
    MenuView::$menus = fn (MenuView $view, MenuBar $bar) => $bar->with(Menu::make('View',
        Toggle::make('Wrap', on: $view->on, onToggle: fn (bool $on) => $view->on = $on),
    ));
    View::render(MenuView::class);

    foreach ([true, false, true] as $expected) {
        nativeBar()->choose('View/Wrap');
        turnLoop();

        expect(menuView()->on)->toBe($expected)
            ->and(nativeBar()->at('View/Wrap')->on)->toBe($expected);
    }
});

it('keeps what the user chose on a toggle nothing controls', function (): void {
    $wrap = fn (bool $on) => MenuBar::make(Menu::make('View', 'view', Toggle::make('Wrap', on: $on, id: 'wrap')));
    openMenus(['app' => $wrap(false), 'on' => $wrap(true), 'bare' => MenuBar::make(tag('bare'))], 'app');

    nativeBar()->choose('View/Wrap');
    turnLoop();
    expect(nativeBar()->at('View/Wrap')->on)->toBeTrue();

    goPage(menu: fn (MenuBar $bar) => $bar->with(tag('other')));
    expect(nativeBar()->at('View/Wrap')->on)->toBeTrue();

    goPage(menu: 'bare');
    goPage(name: '/menus-back');
    expect(nativeBar()->at('View/Wrap')->on)->toBeTrue();
});

it('lets a definition with another value take over', function (): void {
    $wrap = fn (bool $on) => MenuBar::make(Menu::make('View', 'view', Toggle::make('Wrap', on: $on, id: 'wrap')));
    openMenus(['app' => $wrap(false), 'on' => $wrap(true)], 'app');

    nativeBar()->choose('View/Wrap');
    nativeBar()->choose('View/Wrap');
    expect(nativeBar()->at('View/Wrap')->on)->toBeFalse();

    Menus::window(appWindow(), 'on');
    expect(nativeBar()->at('View/Wrap')->on)->toBeTrue();

    Menus::window(appWindow(), 'app');
    expect(nativeBar()->at('View/Wrap')->on)->toBeFalse();
});

/*
|----------------------------------------------------------------------
| Windows
|----------------------------------------------------------------------
*/

it('follows the bar it shares, live, and runs that window\'s handlers', function (): void {
    openMenus(['app' => fileBar()], 'app');
    MenuView::$menus = fn (MenuView $view, MenuBar $bar) => $bar->with(Menu::make('Count',
        Item::make("Add {$view->count}", onClick: fn () => $view->count++),
    ));
    ToolkitWindows::open('tools', 300, 200, Menus::sharedWith(appWindow()));
    expect(nativeBar('tools')->titles())->toBe(['File']);

    View::render(MenuView::class);
    expect(nativeBar('tools')->titles())->toBe(['File', 'Count']);

    nativeBar('tools')->choose('Count/Add 0');
    turnLoop();

    expect(menuView()->count)->toBe(1)
        ->and(nativeBar()->in('Count'))->toBe(['Add 1'])
        ->and(nativeBar('tools')->in('Count'))->toBe(['Add 1']);

    goPage(fn () => Menus::page(MenuBar::make(tag('page'))));

    expect(nativeBar('tools')->tree())->toBe(nativeBar()->tree());
});

it('shares the marks with the bar', function (): void {
    openMenus(['app' => MenuBar::make(Menu::make('View', 'view', Toggle::make('Wrap', on: false, id: 'wrap')))], 'app');
    ToolkitWindows::open('tools', 300, 200, Menus::sharedWith(appWindow()));

    nativeBar('tools')->choose('View/Wrap');

    expect(nativeBar()->at('View/Wrap')->on)->toBeTrue()
        ->and(nativeBar('tools')->at('View/Wrap')->on)->toBeTrue();

    nativeBar()->choose('View/Wrap');

    expect(nativeBar('tools')->at('View/Wrap')->on)->toBeFalse();
});

it('follows through a window that shares too', function (): void {
    openMenus(['app' => fileBar(), 'other' => MenuBar::make(tag('other'))], 'app');
    ToolkitWindows::open('second', 300, 200, Menus::sharedWith(appWindow()));
    ToolkitWindows::open('third', 300, 200, Menus::sharedWith('second'));

    Menus::window(appWindow(), 'other');

    expect(nativeBar('third')->titles())->toBe(['OTHER']);
});

it('picks up a window that opens after the one sharing its bar', function (): void {
    openMenus();
    ToolkitWindows::open('tools', 300, 200, Menus::sharedWith('editor'));
    expect($this->reports->messages())->toHaveCount(1);

    ToolkitWindows::open('editor', 300, 200, MenuBar::make(tag('editor')));

    expect(nativeBar('tools')->titles())->toBe(['EDITOR']);
});

it('reports sharing in a circle, and keeps both bars', function (): void {
    openMenus(['app' => fileBar()], 'app');
    ToolkitWindows::open('tools', 300, 200, Menus::sharedWith(appWindow()));

    Menus::window(appWindow(), Menus::sharedWith('tools'));

    expect(nativeBar()->titles())->toBe(['File'])
        ->and(nativeBar('tools')->titles())->toBe(['File'])
        ->and($this->reports->messages()[0])->toContain('share a bar in a circle');
});

it('releases the bar while the window closes, and leaves a sharer its last one', function (): void {
    openMenus(['app' => fileBar()], 'app');
    $editor = ToolkitWindows::open('editor', 300, 200);
    ToolkitWindows::open('tools', 300, 200, Menus::sharedWith('editor'));
    $bar = nativeBar('editor');

    $editor->close();

    expect($bar->released)->toBeTrue()
        ->and(nativeBar('tools')->titles())->toBe(['File'])
        ->and($this->reports->messages())->toBe([]);

    ToolkitWindows::open('editor', 300, 200, MenuBar::make(tag('again')));

    expect(nativeBar('tools')->titles())->toBe(['AGAIN']);
});

it('shows a bar from the focus callback, working nothing out', function (): void {
    openMenus(['app' => fileBar()], 'app', mac: true);
    View::render(MenuView::class);
    $tools = ToolkitWindows::open('tools', 300, 200, MenuBar::make(tag('tools')));
    $main = Presentation::driver()->window();
    expect(FakeMenuBar::$shown)->toBe(['']);    // no window is focused yet
    [$asked, nativeBar()->ops] = [menuView()->asked, []];

    $main->nativeFocused();
    $tools->nativeFocused();
    $main->nativeUnfocused();    // late: the focus has already moved
    $tools->nativeUnfocused();

    expect(FakeMenuBar::$shown)->toBe(['', appWindow(), 'tools', ''])
        ->and(menuView()->asked)->toBe($asked)
        ->and(nativeBar()->ops)->toBe([])
        ->and($this->loop->posted)->toBe([]);
});

it('shows the default bar when the focused window closes, on macOS', function (): void {
    openMenus(['app' => fileBar()], 'app', mac: true);
    $tools = ToolkitWindows::open('tools', 300, 200);
    $tools->nativeFocused();

    $tools->close();

    expect(FakeMenuBar::$shown)->toBe(['', 'tools', ''])
        ->and(nativeBar('')->titles())->toBe(['topside-tests', 'File']);
});

it('has no bar for no window anywhere but macOS', function (): void {
    openMenus(['app' => fileBar()], 'app', mac: false);

    expect(ToolkitAdapters::driver()->bars)->not->toHaveKey('');
});

it('works menus out for a toolkit with no bar yet', function (): void {
    ToolkitAdapters::driver()->withoutMenuBar();
    openMenus(['app' => fileBar()], 'app');

    goPage(fn () => Menus::page(fn (MenuBar $bar) => $bar->with(tag('page'))));
    Presentation::driver()->window()->close();

    expect(ToolkitAdapters::driver()->bars)->toBe([])
        ->and($this->reports->messages())->toBe([]);
});

it('shows what a toolkit cannot do disabled, and says so once', function (): void {
    openMenus(['app' => MenuBar::make(Standard::windowMenu())]);
    FakeMenuBar::$unsupported = [StandardKind::FULLSCREEN];
    $log = new class {
        public array $warnings = [];

        public function warning(string $message): void
        {
            $this->warnings[] = $message;
        }
    };
    $this->app->registerInstance('log', $log);

    Menus::window(appWindow(), 'app');
    ToolkitWindows::open('tools', 300, 200, 'app');

    expect(nativeBar()->at('Window/Fullscreen')->enabled)->toBeFalse()
        ->and(nativeBar()->at('Window/Minimize')->enabled)->toBeTrue()
        ->and($log->warnings)->toBe(["FakeMenuBar cannot do the standard menu item 'fullscreen': it is shown disabled."]);
});

/*
|----------------------------------------------------------------------
| Listening
|----------------------------------------------------------------------
*/

it('runs #[OnMenu] while the view is on screen, and renders it again', function (): void {
    openMenus(['app' => fileBar()], 'app');
    View::render(MenuView::class);
    $view = menuView();

    nativeBar()->choose('File/Save');
    nativeBar()->choose('File/Open');
    turnLoop();

    expect($view->heard)->toBe(['menu.clicked.file.save', 'opened'])
        ->and($view->count)->toBe(1)
        ->and($view->renders)->toBe(3);

    View::render(MenuView::class);
    nativeBar()->choose('File/Save');
    turnLoop();

    expect($view->heard)->toHaveCount(2)
        ->and(menuView()->heard)->toHaveCount(1);
});

it('keeps Menus::on() until the window opens another route', function (): void {
    openMenus(['app' => fileBar()], 'app');
    $heard = [];
    $listen = function (MenuMail $mail) use (&$heard): void {
        $heard[] = $mail->path();
    };
    goPage(fn () => Menus::on('file.save', $listen));

    nativeBar()->choose('File/Save');
    nativeBar()->choose('File/Open');
    turnLoop();
    expect($heard)->toBe(['file.save']);

    goPage(name: '/menus-next');
    nativeBar()->choose('File/Save');
    turnLoop();
    expect($heard)->toBe(['file.save']);
});

it('ends a listener that an earlier one navigated away from', function (): void {
    openMenus(['app' => fileBar()], 'app');
    $late = 0;
    goPage(function () use (&$late): void {
        Menus::on('file.save', fn () => goPage(name: '/menus-away'));
        Menus::on('file.save', function () use (&$late): void { $late++; });
    });

    nativeBar()->choose('File/Save');
    turnLoop();

    expect($late)->toBe(0);
});

/*
|----------------------------------------------------------------------
| A bad menu
|----------------------------------------------------------------------
*/

it('reports a bad menu and keeps the last good bar', function (Closure $break): void {
    openMenus(['app' => fileBar(), 'callback' => MenuBar::make(Menu::make('M', Item::make('Run', onClick: fn () => null)))], 'app');
    View::render(MenuView::class);
    $good = nativeBar()->tree();

    $break();
    turnLoop();

    expect(nativeBar()->tree())->toBe($good)
        ->and($this->reports->reported)->not->toBe([])
        ->and($this->reports->reported[0])->toBeInstanceOf(MenuException::class)
        ->and($this->reports->messages()[0])->toContain("window '".appWindow()."'")
        ->and($this->reports->reported[0]->getPrevious())->not->toBeNull();
})->with([
    'a profile that is not there' => [fn () => Menus::window(appWindow(), 'nope')],
    'a profile with a callback' => [fn () => Menus::window(appWindow(), 'callback')],
    'a route closure that cannot' => [fn () => goPage(menu: fn (MenuBar $bar) => $bar->extend('help', fn ($m) => $m))],
    'a route closure that returns no bar' => [fn () => goPage(menu: fn () => 'oops')],
    'a route closure with a bug' => [fn () => goPage(menu: fn () => throw new LogicException('bug'))],
    'a page closure that cannot' => [fn () => goPage(fn () => Menus::page(fn (MenuBar $bar) => $bar->item('file.none', fn ($i) => $i)))],
    'a controller building a bad bar' => [fn () => goPage(fn () => Menus::page(Menus::profile('app')->extend('help', fn ($m) => $m)))],
    'two items with one shortcut' => [fn () => goPage(menu: fn (MenuBar $bar) => $bar->extend('file', fn (Menu $file) => $file->append(Item::make('Sync', id: 'sync')->shortcut(Shortcut::primary('S')))))],
    'the view' => [function (): void {
        MenuView::$menus = fn ($view, MenuBar $bar) => $bar->extend('help', fn ($m) => $m);
        Menus::manager()->refresh(appWindow());
    }],
    'the toolkit' => [function (): void {
        nativeBar()->failInsert = 'Refused';
        Menus::window(appWindow(), MenuBar::make(Menu::make('Refused')));
    }],
]);

it('keeps running when a view handler, a menu callback or a listener builds a bad menu', function (): void {
    openMenus(['app' => fileBar()], 'app');
    $bad = fn () => Menus::profile('app')->extend('help', fn ($m) => $m);
    MenuView::$menus = fn ($view, MenuBar $bar) => $bar->item('file.open', fn (Item $open) => $open->onClick($bad));
    MenuView::$onButton = $bad;
    goPage(function () use ($bad): void {
        View::render(MenuView::class);
        Menus::on('file.save', $bad);
    });

    at(childNamesOfScreen()[0].'.press')->click();
    nativeBar()->choose('File/Open');
    nativeBar()->choose('File/Save');
    turnLoop();

    expect($this->reports->reported)->toHaveCount(3)
        ->and(nativeBar()->titles())->toBe(['File']);
});

it('leaves an exception that is no menu\'s to the application', function (): void {
    openMenus();
    Menus::window(appWindow(), MenuBar::make(Menu::make('M', Item::make('Run', onClick: fn () => throw new LogicException('the app\'s')))));

    nativeBar()->choose('M/Run');

    expect(fn () => turnLoop())->toThrow(LogicException::class, "the app's");
});

it('reports one failure once, until the bar works again', function (): void {
    openMenus(['app' => fileBar()], 'app');

    Menus::window(appWindow(), 'nope');
    Menus::manager()->refresh(appWindow());
    expect($this->reports->reported)->toHaveCount(1);

    Menus::window(appWindow(), 'app');
    Menus::window(appWindow(), 'nope');
    expect($this->reports->reported)->toHaveCount(2);
});

it('opens a window whose menu is bad', function (): void {
    openMenus();

    $window = ToolkitWindows::open('tools', 300, 200, 'nope');

    expect($window->isOpen())->toBeTrue()
        ->and(nativeBar('tools')->titles())->toBe([])
        ->and($this->reports->messages())->toBe(["The menus of window 'tools': No menu profile named 'nope'. None is defined."]);
});

it('does not loop when a view\'s menu() asks for a page', function (): void {
    openMenus(['app' => fileBar()], 'app');
    MenuView::$menus = function ($view, MenuBar $bar) {
        Menus::page('app');

        return $bar;
    };

    View::render(MenuView::class);

    expect(nativeBar()->titles())->toBe(['File']);
});

it('lets a callback close the window it is in', function (): void {
    openMenus();
    MenuView::$menus = fn ($view, MenuBar $bar) => $bar->with(Menu::make('File', 'file',
        Item::make('Close', id: 'close', onClick: fn () => Presentation::driver()->window()->close()),
    ));
    View::render(MenuView::class);

    nativeBar()->choose('File/Close');

    expect(publicMail(turnLoop()))->toContain('menu.clicked.file.close')
        ->and($this->reports->messages())->toBe([]);
});

it('lets a button close the window it is in', function (): void {
    openMenus();
    MenuView::$onButton = fn () => Presentation::driver()->window()->close();
    View::render(MenuView::class);

    at(childNamesOfScreen()[0].'.press')->click();
    turnLoop();

    expect($this->reports->messages())->toBe([]);
});
