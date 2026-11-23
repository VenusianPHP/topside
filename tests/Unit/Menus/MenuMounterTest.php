<?php

declare(strict_types=1);

use Surface\Toolkits\ToolkitAdapters;
use Surface\Toolkits\Menus\About;
use Surface\Toolkits\Menus\Choice;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\Separator;
use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\Standard;
use Surface\Toolkits\Menus\Toggle;
use Surface\Toolkits\Menus\Runtime\MenuMounter;
use Surface\Toolkits\Menus\Runtime\MenuPlanner;
use Venusian\Topside\Tests\Fakes\Menus\FakeMenuBar;

beforeEach(function (): void {
    FakeMenuBar::$unsupported = [];
    $this->native = new FakeMenuBar(ToolkitAdapters::driver()->connect());
    $this->mounter = new MenuMounter($this->native);
    $this->plan = fn (?MenuBar $bar, bool $mac = false): array => (new MenuPlanner($mac, 'Probe', fn () => true))->plan($bar, []);
    $this->show = function (?MenuBar $bar, bool $mac = false): array {
        $this->native->ops = [];
        $this->mounter->show(($this->plan)($bar, $mac));

        return $this->native->ops;
    };
});

it('inserts a menu, then what is in it', function (): void {
    expect(($this->show)(MenuBar::make(Menu::make('File', 'file', Item::make('Open', id: 'open'), Separator::make(), Menu::make('More', Standard::copy())))))
        ->toBe([
            ['insert', '', 0, 'File'],
            ['insert', 'File', 0, 'Open'],
            ['insert', 'File', 1, '-'],
            ['insert', 'File', 2, 'More'],
            ['insert', 'More', 0, 'Copy'],
        ]);
});

it('does nothing for an unchanged bar', function (): void {
    $bar = fn (): MenuBar => fileBar()->with(Standard::editMenu(), Menu::make('View', 'view',
        Toggle::make('Wrap', on: true, id: 'wrap'),
        Choice::make(['a' => 'A', 'b' => 'B'], 'a', id: 'mode'),
        Item::make('Bold', onClick: fn () => null),
    ));
    ($this->show)($bar());

    expect(($this->show)($bar()))->toBe([]);
});

it('tells only what changed, for each thing that can', function (Closure $before, Closure $after, array $changes): void {
    ($this->show)(MenuBar::make(Menu::make('M', 'm', $before())));
    $ops = ($this->show)(MenuBar::make(Menu::make('M', 'm', $after())));

    expect($ops)->toHaveCount(1)
        ->and($ops[0][0])->toBe('change')
        ->and($ops[0][2])->toEqual($changes);
})->with([
    'label' => [fn () => Item::make('A', id: 'x'), fn () => Item::make('B', id: 'x'), ['label' => 'B']],
    'enabled' => [fn () => Item::make('A', id: 'x'), fn () => Item::make('A', id: 'x')->enabled(false), ['enabled' => false]],
    'on' => [fn () => Toggle::make('A', on: false, id: 'x'), fn () => Toggle::make('A', on: true, id: 'x'), ['on' => true]],
    'chosen' => [fn () => Choice::make(['a' => 'A', 'b' => 'B'], 'a', id: 'x'), fn () => Choice::make(['a' => 'A', 'b' => 'B'], 'b', id: 'x'), ['chosen' => 'b']],
    'shortcut' => [fn () => Item::make('A', id: 'x'), fn () => Item::make('A', id: 'x')->shortcut(Shortcut::primary('S')), ['shortcut' => Shortcut::primary('S')]],
]);

it('changes a callback without telling the toolkit, and runs the new one', function (): void {
    ($this->show)(MenuBar::make(Menu::make('M', 'm', Item::make('A', id: 'x'))));
    $run = fn () => null;

    expect(($this->show)(MenuBar::make(Menu::make('M', 'm', Item::make('A', id: 'x', onClick: $run)))))->toBe([])
        ->and($this->mounter->find($this->native->uuid('M/A'))->planned->source->callback())->toBe($run);
});

it('inserts, removes and moves in place', function (): void {
    $menu = fn (string ...$ids): MenuBar => MenuBar::make(Menu::make('M', 'm', ...array_map(fn (string $id) => Item::make($id, id: $id), $ids)));
    ($this->show)($menu('a', 'b', 'c'));

    expect(($this->show)($menu('a', 'x', 'b', 'c')))->toBe([['insert', 'M', 1, 'x']])
        ->and(($this->show)($menu('a', 'b', 'c')))->toBe([['remove', 'x']])
        ->and(($this->show)($menu('c', 'a', 'b')))->toBe([['move', 'c', 0]])
        ->and($this->native->in('M'))->toBe(['c', 'a', 'b']);
});

it('removes a menu in one operation, with what is under it', function (): void {
    ($this->show)(fileBar()->with(Standard::editMenu()));

    expect(($this->show)(fileBar()))->toBe([['remove', 'Edit']])
        ->and($this->native->nodes)->toHaveCount(3);
});

it('disables what is in a disabled menu, node by node', function (): void {
    ($this->show)(fileBar());

    expect(($this->show)(fileBar()->extend('file', fn (Menu $file) => $file->enabled(false))))->toBe([
        ['change', 'File', ['enabled' => false]],
        ['change', 'Open', ['enabled' => false]],
        ['change', 'Save', ['enabled' => false]],
    ]);
});

it('makes again only a node whose shape changed', function (Closure $before, Closure $after): void {
    ($this->show)(MenuBar::make(Menu::make('M', 'm', Item::make('Keep', id: 'keep'), $before())));
    $ops = ($this->show)(MenuBar::make(Menu::make('M', 'm', Item::make('Keep', id: 'keep'), $after())));

    expect(array_column($ops, 0))->toBe(['remove', 'insert'])
        ->and($ops[1][2])->toBe(1);
})->with([
    'kind' => [fn () => Item::make('A', id: 'x'), fn () => Toggle::make('A', on: false, id: 'x')],
    'options' => [fn () => Choice::make(['a' => 'A'], 'a', id: 'x'), fn () => Choice::make(['a' => 'A', 'b' => 'B'], 'a', id: 'x')],
    'replaced' => [fn () => Standard::copy(), fn () => Standard::copy()->onClick(fn () => null)],
    'panel' => [fn () => About::panel('One'), fn () => About::panel('Two')],
]);

it('works out a platform once, so an adapter has no rule of its own', function (): void {
    $bar = MenuBar::make(
        Menu::make('File', 'file', Item::make('Open', id: 'open')),
        Menu::application(Standard::hide(), Standard::quit()),
    );
    ($this->show)($bar, true);

    expect($this->native->titles())->toBe(['Probe', 'File'])
        ->and($this->native->at('Probe')->application)->toBeTrue()
        ->and($this->native->in('Probe'))->toBe(['Hide Probe', 'Quit Probe'])
        ->and($this->native->at('Probe/Quit Probe')->shortcut->describe(true))->toBe('Cmd+Q');

    ($this->show)($bar, false);

    expect($this->native->titles())->toBe(['Probe', 'File'])
        ->and($this->native->in('Probe'))->toBe(['Quit']);
});

it('gives a bar with no application menu an empty one on macOS only', function (): void {
    ($this->show)(fileBar(), true);

    expect($this->native->titles())->toBe(['Probe', 'File'])
        ->and($this->native->in('Probe'))->toBe([]);

    ($this->show)(fileBar(), false);

    expect($this->native->titles())->toBe(['File']);

    ($this->show)(null, true);

    expect($this->native->titles())->toBe([]);
});

it('puts the last good bar back when the toolkit fails partway', function (): void {
    ($this->show)(fileBar());
    $good = $this->native->tree();
    $this->native->failInsert = 'Bad';

    $next = MenuBar::make(Menu::make('New', Separator::make()), Menu::make('M', 'm', Item::make('Fine', id: 'fine'), Item::make('Bad', id: 'bad')));

    expect(fn () => ($this->show)($next))->toThrow(RuntimeException::class, "native refused 'Bad'")
        ->and($this->native->tree())->toBe($good);

    $this->native->failInsert = null;
    ($this->show)($next);

    expect($this->native->titles())->toBe(['New', 'M']);
});

it('shows, after any run of changes, what building that bar from nothing shows', function (int $seed): void {
    mt_srand($seed);
    $random = function (): MenuBar {
        $menus = [];
        foreach (range('a', 'f') as $name) {
            if (mt_rand(0, 3) === 0) {
                continue;
            }
            $nodes = [];
            foreach (range(1, 6) as $n) {
                $node = match (mt_rand(0, 8)) {
                    0, 1 => Item::make("I{$n}", id: "n{$n}"),
                    2 => Item::make("J{$n}", id: "n{$n}")->enabled(false),
                    3 => Toggle::make("T{$n}", on: (bool) mt_rand(0, 1), id: "n{$n}"),
                    4 => Choice::make(['a' => 'A', 'b' => 'B'], mt_rand(0, 1) ? 'a' : 'b', id: "n{$n}"),
                    5 => Separator::make(),
                    6 => Menu::make("S{$n}", "n{$n}", Item::make('Deep', id: 'deep')->enabled((bool) mt_rand(0, 1))),
                    default => null,
                };
                if (! is_null($node)) {
                    $nodes[] = $node;
                }
            }
            shuffle($nodes);
            $menus[] = Menu::make(strtoupper($name), $name, ...$nodes)->enabled(mt_rand(0, 5) > 0);
        }
        shuffle($menus);

        return MenuBar::make(...$menus);
    };

    foreach (range(1, 150) as $step) {
        $bar = $random();
        $mac = (bool) mt_rand(0, 1);
        ($this->show)($bar, $mac);

        $fresh = new FakeMenuBar(ToolkitAdapters::driver()->session());
        (new MenuMounter($fresh))->show(($this->plan)($bar, $mac));

        expect($this->native->tree())->toBe($fresh->tree())
            ->and(($this->show)($bar, $mac))->toBe([]);
    }
})->with([1, 2, 3, 4, 5, 6, 7, 8]);
