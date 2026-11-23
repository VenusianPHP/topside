<?php

declare(strict_types=1);

use Surface\Toolkits\View;
use Surface\Toolkits\Menus;
use Surface\Toolkits\Presentation;
use Surface\Toolkits\Menus\About;
use Surface\Toolkits\Menus\Choice;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\Separator;
use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\Standard;
use Surface\Toolkits\Menus\Toggle;
use Surface\Toolkits\Views\Sharp\SharpCompileError;
use Venusian\Topside\Tests\Fakes\Views\MenuView;

beforeEach(fn () => sharpApp());
afterEach(fn () => dropSharpApp());

/** Write a template with a menu block, and put it on screen. */
function menuTemplate(string $menu, string $script = '', string $marker = '@menu'): object
{
    template('page', ($script === '' ? '' : "@script\n{$script}\n@endscript\n")
        ."{$marker}\n{$menu}\n@endmenu\n"
        ."@template\n<Column><Label>page</Label></Column>\n@endtemplate\n");
    View::render('page');

    return Presentation::driver()->view()->component;
}

/** What the bar shows when menu() returns $write's bar instead. */
function writtenInMenu(Closure $write): array
{
    MenuView::$menus = fn ($view, MenuBar $bar) => $write($bar);
    View::render(MenuView::class);

    return nativeBar()->tree();
}

it('gives the design\'s example the bar the same menus written in menu() give', function (): void {
    openMenus(['app' => fileBar()], 'app');
    $design = file_get_contents(dirname(__DIR__, 3).'/.okf/design/menus.md');
    preg_match('/```\n@menu\n(.*?)@endmenu/s', $design, $example);

    menuTemplate(rtrim($example[1]), "public bool \$dirty = false;\npublic bool \$wrap = true;\npublic function save(): void {}");
    $sharp = nativeBar()->tree();

    $written = writtenInMenu(fn (MenuBar $bar) => $bar
        ->item('file.save', fn (Item $save) => $save->onClick(fn () => null)->enabled(false))
        ->with(Menu::make('Format', 'format',
            Toggle::make('Word Wrap', on: true, onToggle: fn (bool $on) => null),
            Separator::make(),
            Item::make('Export PDF', id: 'export'),
        )));

    expect($sharp)->toBe($written)
        ->and($this->reports->messages())->toBe([]);
});

it('gives every tag the bar menu() gives', function (string $markup, Closure $write): void {
    openMenus(['app' => fileBar()], 'app');
    menuTemplate($markup, "public string \$name = 'Probe';\npublic array \$modes = ['a' => 'A', 'b' => 'B'];");
    $sharp = nativeBar()->tree();

    expect($this->reports->messages())->toBe([])
        ->and($sharp)->toEqual(writtenInMenu($write));
})->with([
    'Menu, Item' => [
        '<Menu title="Help" id="help" enabled="false"><Item label="Guide {{ $name }}" id="guide" shortcut="F1"/></Menu>',
        fn (MenuBar $bar) => $bar->with(Menu::make('Help', 'help', Item::make('Guide Probe', id: 'guide')->shortcut(Shortcut::key('F1')))->enabled(false)),
    ],
    'Toggle, Choice, Separator' => [
        '<Menu title="View" id="view"><Toggle label="Wrap" on id="wrap"/><Separator/><Choice :options="$modes" chosen="b" id="mode"/></Menu>',
        fn (MenuBar $bar) => $bar->with(Menu::make('View', 'view', Toggle::make('Wrap', on: true, id: 'wrap'), Separator::make(), Choice::make(['a' => 'A', 'b' => 'B'], 'b', id: 'mode'))),
    ],
    'Standard' => [
        '<Menu title="Tools"><Standard kind="copy" label="Duplicate"/><Standard kind="close-window" :shortcut="null"/><Standard kind="SELECT_ALL"/></Menu>',
        fn (MenuBar $bar) => $bar->with(Menu::make('Tools', Standard::copy()->label('Duplicate'), Standard::closeWindow()->shortcut(null), Standard::selectAll())),
    ],
    'EditMenu, WindowMenu' => [
        '<EditMenu/><WindowMenu title="Fenster"/>',
        fn (MenuBar $bar) => $bar->with(Standard::editMenu(), Standard::windowMenu()->titled('Fenster')),
    ],
    'AboutPanel in the application menu' => [
        '<Menu application><AboutPanel name="Probe" version="1.0"/><Standard kind="quit"/></Menu>',
        fn (MenuBar $bar) => $bar->with(Menu::application(About::panel(name: 'Probe', version: '1.0'), Standard::quit())),
    ],
    'AboutItem in a submenu' => [
        '<Menu title="Help" id="help"><Menu title="More" id="more"><AboutItem id="about"/></Menu></Menu>',
        fn (MenuBar $bar) => $bar->with(Menu::make('Help', 'help', Menu::make('More', 'more', About::item(id: 'about')))),
    ],
]);

it('replaces a menu that is there, and changes it with extend', function (): void {
    openMenus(['app' => fileBar()], 'app');

    menuTemplate('<Menu title="File" id="file"><Item label="New" id="new"/></Menu>');
    expect(nativeBar()->in('File'))->toBe(['New']);

    menuTemplate('<Menu key="file" extend><Item label="New" id="new"/></Menu>');
    expect(nativeBar()->in('File'))->toBe(['Open', 'Save', 'New']);
});

it('changes a node whose id is there, only in what was written', function (): void {
    openMenus(['app' => fileBar()], 'app');

    menuTemplate('<Menu key="file" extend title="Datei"><Item id="open" :enabled="false"/><Item id="save" label="Sichern"/></Menu>');

    expect(nativeBar()->in('Datei'))->toBe(['Open', 'Sichern'])
        ->and(nativeBar()->at('Datei/Open')->enabled)->toBeFalse()
        ->and(nativeBar()->at('Datei/Sichern')->shortcut->describe())->toBe('Primary+S');
});

it('changes an inherited toggle, choice, standard item and About in place', function (): void {
    openMenus(['app' => MenuBar::make(Menu::make('M', 'm',
        Toggle::make('Wrap', on: false, id: 'wrap'),
        Choice::make(['a' => 'A', 'b' => 'B'], 'a', id: 'mode'),
        Standard::copy()->id('copy'),
        Menu::make('Export', 'export', Item::make('PDF', id: 'pdf')),
        About::item(id: 'about'),
    ))], 'app');

    $view = menuTemplate(
        '<Menu key="m" extend>'
        .'<Toggle id="wrap" @toggle="fn ($on) => $this->got = (string) (int) $on"/>'
        .'<Choice id="mode" chosen="b"/>'
        .'<Standard id="copy" label="Duplicate"/>'
        .'<Menu id="export"><Item id="pdf" label="PDF..."/><Item label="PNG" id="png"/></Menu>'
        .'<AboutItem id="about" label="Who"/>'
        .'</Menu>',
        "public string \$got = '';",
    );

    expect($this->reports->messages())->toBe([])
        ->and(nativeBar()->in('M'))->toBe(['Wrap', 'A|B', 'Duplicate', 'Export', 'Who'])
        ->and(nativeBar()->at('M/A|B')->chosen)->toBe('b')
        ->and(nativeBar()->in('M/Export'))->toBe(['PDF...', 'PNG']);

    nativeBar()->choose('M/Wrap');

    expect(publicMail(turnLoop()))->toBe(['menu.toggled.m.wrap'])
        ->and($view->got)->toBe('1');
});

it('makes a menu whose key is new at the end, with extend too', function (): void {
    openMenus(['app' => fileBar()], 'app');

    menuTemplate('<Menu key="help" title="Help" extend><Item label="Guide" id="guide"/></Menu>');

    expect(nativeBar()->titles())->toBe(['File', 'Help']);

    nativeBar()->choose('Help/Guide');
    expect(publicMail(turnLoop()))->toBe(['menu.clicked.help.guide']);
});

it('makes the block the whole bar with replace', function (): void {
    openMenus(['app' => fileBar()], 'app');

    menuTemplate('<Menu title="Only"/>', marker: '@menu replace');

    expect(nativeBar()->titles())->toBe(['Only']);
});

it('reads the state again after each callback', function (): void {
    openMenus();
    $view = menuTemplate(
        "<Menu title=\"Tabs\">\n@foreach (\$tabs as \$tab)\n<Item label=\"{{ \$tab }}\" @click=\"fn () => \$this->closeTab(\$tab)\"/>\n@endforeach\n@if (count(\$tabs) > 1)\n<Item label=\"Close all\" @click=\"closeAll\"/>\n@endif\n</Menu>",
        "public array \$tabs = ['one', 'two'];\n"
        ."public function closeTab(string \$tab): void { \$this->tabs = array_values(array_diff(\$this->tabs, [\$tab])); }\n"
        ."public function closeAll(): void { \$this->tabs = []; }",
    );
    expect(nativeBar()->in('Tabs'))->toBe(['one', 'two', 'Close all']);

    nativeBar()->choose('Tabs/one');
    turnLoop();

    expect($view->tabs)->toBe(['two'])
        ->and(nativeBar()->in('Tabs'))->toBe(['two']);
});

it('takes menu() and #[OnMenu] in @script', function (): void {
    openMenus(['app' => fileBar()], 'app');
    template('page', <<<'SHARP'
@script
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\OnMenu;

public int $saves = 0;

public function menu(MenuBar $bar): MenuBar
{
    return $bar->with(Menu::make('Added'));
}

#[OnMenu('file.save')]
public function saved(): void
{
    $this->saves++;
}
@endscript
@template
<Column><Label>{{ $saves }}</Label></Column>
@endtemplate
SHARP);
    View::render('page');
    expect(nativeBar()->titles())->toBe(['File', 'Added']);

    nativeBar()->choose('File/Save');
    turnLoop();

    expect(Presentation::driver()->view()->component->saves)->toBe(1);
});

it('takes @menu after @template, with the same lines', function (): void {
    openMenus();
    template('page', "@template\n<Column/>\n@endtemplate\n@menu\n<Menu title=\"After\">\n<Toggle id=\"x\" label=\"X\"/>\n</Menu>\n@endmenu\n");

    expect(fn () => View::render('page'))->toThrow(SharpCompileError::class, 'needs on');

    template('page', "@template\n<Column/>\n@endtemplate\n@menu\n<Menu title=\"After\" id=\"after\">\n<Toggle id=\"x\" label=\"X\" on/>\n</Menu>\n@endmenu\n");
    View::render('page');

    expect(nativeBar()->titles())->toBe(['After']);
});

it('reports what cannot be built, with the line, and keeps the bar', function (string $markup, string $problem): void {
    openMenus(['app' => fileBar()], 'app');

    menuTemplate($markup);

    expect(nativeBar()->titles())->toBe(['File'])
        ->and($this->reports->messages())->toHaveCount(1)
        ->and($this->reports->messages()[0])->toContain($problem);
})->with([
    'nothing to extend' => ["<Menu key=\"help\" extend/>", "@menu line 2, <Menu>: There is no menu with the key 'help' to extend"],
    'nothing to change' => ["<Menu key=\"file\" extend>\n<Item id=\"none\"/>\n</Menu>", "@menu line 3, <Item>: Nothing there has the id 'none' to change"],
    'another kind' => ["<Menu key=\"file\" extend>\n<Toggle id=\"save\"/>\n</Menu>", "@menu line 3, <Toggle>: <Toggle> cannot change item 'Save'"],
    'nothing to do' => ["<Menu title=\"Help\">\n<Item label=\"Guide\"/>\n</Menu>", "Item 'Guide' does nothing"],
]);

it('says what is wrong when it compiles', function (string $markup, string $problem, string $script): void {
    openMenus();

    expect(fn () => menuTemplate($markup, $script))->toThrow(SharpCompileError::class, $problem);
})->with([
    'a tag it has not' => ['<Button/>', '<Button> is not a tag of @menu', ''],
    'a bare item' => ['<Item label="X" id="x"/>', '<Item> goes inside a <Menu>', ''],
    'text' => ['<Menu title="M">words</Menu>', 'Text on its own in @menu', ''],
    'an attribute it has not' => ['<Menu title="M"><Item label="X" id="x" colour="red"/></Menu>', '<Item> has no attribute colour', ''],
    'a callback as an attribute' => ['<Menu title="M"><Item label="X" :onClick="null"/></Menu>', '<Item> has no attribute onClick', ''],
    'an event it has not' => ['<Menu title="M"><Item label="X" @toggle="run"/></Menu>', '<Item> has no @toggle: it has @click', 'public function run(): void {}'],
    'no such method' => ['<Menu title="M"><Item label="X" @click="run"/></Menu>', '@script has no method run()', ''],
    'no title' => ['<Menu/>', '<Menu> needs title', ''],
    'no label' => ['<Menu title="M"><Item id="x"/></Menu>', '<Item> needs label', ''],
    'key without extend' => ['<Menu key="file" title="File"/>', 'key names the menu to extend', ''],
    'a shortcut that is none' => ['<Menu title="M"><Item label="X" id="x" shortcut="Hyper+S"/></Menu>', "'Hyper' is not a modifier", ''],
    'children in an item' => ['<Menu title="M"><Item label="X" id="x"><Separator/></Item></Menu>', '<Item> holds nothing', ''],
    'a copy changed' => ['<Menu title="M"><Item label="X" @click="fn () => $n = 1"/></Menu>', 'changes a copy', 'public int $n = 0;'],
    'menu() beside @menu' => ['<Menu title="M"/>', 'a template has one or the other', "public function menu(\\Surface\\Toolkits\\Menus\\MenuBar \$bar): \\Surface\\Toolkits\\Menus\\MenuBar { return \$bar; }"],
]);

it('refuses a menu() that does not fit', function (): void {
    openMenus();
    template('page', "@script\npublic function menu(): string { return 'x'; }\n@endscript\n@template\n<Column/>\n@endtemplate\n");

    expect(fn () => View::render('page'))->toThrow(SharpCompileError::class, 'menu() is written public function menu(MenuBar $bar): MenuBar');
});
