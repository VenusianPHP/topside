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

function nodeKeys(array $nodes): array
{
    return array_map(fn ($node) => $node->key(), $nodes);
}

it('makes an item that posts mail, one that runs a callback, and one that does both', function (): void {
    $run = fn () => null;

    expect(Item::make('Save', id: 'save')->mailId())->toBe('save')
        ->and(Item::make('Save', id: 'save')->callback())->toBeNull()
        ->and(Item::make('Save', onClick: $run)->callback())->toBe($run)
        ->and(Item::make('Save', onClick: $run)->mailId())->toBeNull()
        ->and(Item::make('Save', id: 'save', onClick: $run)->callback())->toBe($run)
        ->and(Toggle::make('Wrap', on: true, onToggle: $run)->callback())->toBe($run)
        ->and(Choice::make(['a' => 'A'], 'a', onChoose: $run)->callback())->toBe($run);
});

it('cannot build an item that does nothing', function (Closure $build, string $message): void {
    expect($build)->toThrow(MenuException::class, $message);
})->with([
    'item' => [fn () => Item::make('Save'), "Item 'Save' does nothing. Give it id: (it posts menu.clicked.<menu id>.<item id>) or onClick:."],
    'toggle' => [fn () => Toggle::make('Wrap', on: false), "Toggle 'Wrap' does nothing. Give it id: (it posts menu.toggled.<menu id>.<item id>) or onToggle:."],
    'choice' => [fn () => Choice::make(['a' => 'A'], 'a'), 'Choice (A) does nothing. Give it id: (it posts menu.chosen.<menu id>.<item id>) or onChoose:.'],
    'preferences' => [fn () => Standard::preferences(), "Standard item 'preferences' does nothing. Give it id: (it posts menu.clicked.<menu id>.<item id>) or onClick:."],
    'about' => [fn () => About::item(), "Standard item 'about' does nothing. Give it id: (it posts menu.clicked.<menu id>.<item id>) or onClick:, or use About::panel()."],
]);

it('needs to be told whether a toggle starts on', function (): void {
    expect(fn () => Toggle::make('Wrap', id: 'wrap'))->toThrow(ArgumentCountError::class);
});

it('refuses an empty label or title', function (): void {
    expect(fn () => Item::make('', id: 'save'))->toThrow(MenuException::class, 'A menu item needs a label.')
        ->and(fn () => Toggle::make('', on: false, id: 'wrap'))->toThrow(MenuException::class, 'A menu toggle needs a label.')
        ->and(fn () => Standard::quit()->label(''))->toThrow(MenuException::class, "Standard item 'quit': the label cannot be empty.")
        ->and(fn () => Menu::make(''))->toThrow(MenuException::class, 'A menu needs a title.');
});

it('takes an id of lower case letters, digits and dashes', function (string $id): void {
    expect(Item::make('Save', id: $id)->id)->toBe($id);
})->with(['save', 'save-as', '2024']);

it('refuses any other id', function (string $id): void {
    $rule = "the id '{$id}' must be lower case letters, digits and '-', like 'save-as'.";

    expect(fn () => Item::make('Save', id: $id))->toThrow(MenuException::class, "Item 'Save': {$rule}")
        ->and(fn () => Menu::make('File', $id))->toThrow(MenuException::class, "Menu 'File': {$rule}");
})->with(['Save', 'file save', 'file.save', 'save_as', '', "save\n"]);

it('refuses a choice that cannot have exactly one option chosen', function (): void {
    expect(fn () => Choice::make([], 'a', id: 'mode'))->toThrow(MenuException::class, 'A menu choice needs at least one option.')
        ->and(fn () => Choice::make(['a' => 'A', 'b' => 'B'], 'c', id: 'mode'))->toThrow(MenuException::class, "Menu choice: 'c' is not one of its options (a, b).")
        ->and(fn () => Choice::make(['a' => ''], 'a', id: 'mode'))->toThrow(MenuException::class, "Menu choice: the option 'a' needs a label.")
        ->and(fn () => Choice::make(['a' => 'A'], 'a', id: 'mode')->chosen('z'))->toThrow(MenuException::class, "'z' is not one of its options");
});

it('takes a title, then optionally an id, then the nodes', function (): void {
    $open = Item::make('Open', id: 'open');
    $line = Separator::make();

    expect(Menu::make('File', 'file', $open, $line)->id)->toBe('file')
        ->and(Menu::make('File', 'file', $open, $line)->nodes)->toBe([$open, $line])
        ->and(Menu::make('File', $line)->id)->toBeNull()
        ->and(Menu::make('File', $line)->key())->toBe('File')
        ->and(Menu::make('File', 'file')->key())->toBe('file');
});

it('says where a menu id goes', function (): void {
    expect(fn () => Menu::make('File', id: 'file'))
        ->toThrow(MenuException::class, "Menu::make() takes no 'id:'. A menu's id is its second argument: Menu::make('File', 'file', ...).")
        ->and(fn () => Menu::make('File', 'file', 'Save'))
        ->toThrow(MenuException::class, "Menu::make() takes a title, an id and then nodes, not the string 'Save' among them.");
});

it('needs an id on every menu above an item with an id', function (): void {
    expect(fn () => Menu::make('File', Item::make('Save', id: 'save')))
        ->toThrow(MenuException::class, "Menu 'File' holds item 'Save', which has the id 'save', so the menu needs an id too, as its second argument: Menu::make('File', '<id>', ...).")
        ->and(fn () => Menu::make('File', Menu::make('Export', 'export', Item::make('PDF', id: 'pdf'))))
        ->toThrow(MenuException::class, "Menu 'File' holds item 'PDF'")
        ->and(fn () => Menu::make('File', Separator::make())->append(Item::make('Save', id: 'save')))
        ->toThrow(MenuException::class, "Menu 'File' holds item 'Save'")
        ->and(fn () => Menu::make('Edit', Standard::copy()->id('copy')))
        ->toThrow(MenuException::class, "Menu 'Edit' holds standard item 'copy'")
        ->and(Menu::make('Format', Item::make('Bold', onClick: fn () => null), Standard::copy())->id)->toBeNull();
});

it('refuses two nodes of one menu with one id', function (): void {
    expect(fn () => Menu::make('File', 'file', Item::make('Save', id: 'save'), Toggle::make('Autosave', on: true, id: 'save')))
        ->toThrow(MenuException::class, "Menu 'File': item 'Save' and toggle 'Autosave' share the id 'save'.")
        ->and(fn () => Menu::make('File', 'file', Menu::make('Export', 'export'), Item::make('Export', id: 'export')))
        ->toThrow(MenuException::class, "Menu 'File': menu 'Export' and item 'Export' share the id 'export'.");
});

it('refuses two nodes of a menu that cannot be told apart', function (): void {
    expect(fn () => Menu::make('Edit', Standard::copy(), Standard::copy()))
        ->toThrow(MenuException::class, "Menu 'Edit': standard item 'copy' and standard item 'copy' cannot be told apart. Give one of them an id.")
        ->and(Menu::make('File', 'file', Item::make('Save', id: 'a'), Separator::make(), Item::make('Save', id: 'b'), Separator::make())->nodes)
        ->toHaveCount(4);
});

it('places, replaces and removes nodes by key', function (): void {
    $open = Item::make('Open', id: 'open');
    $save = Item::make('Save', id: 'save');
    $close = Item::make('Close', id: 'close');
    $file = Menu::make('File', 'file', $open, $close);

    expect(nodeKeys($file->before('close', $save)->nodes))->toBe(['open', 'save', 'close'])
        ->and(nodeKeys($file->after('open', $save)->nodes))->toBe(['open', 'save', 'close'])
        ->and(nodeKeys($file->append($save)->nodes))->toBe(['open', 'close', 'save'])
        ->and(nodeKeys($file->prepend($save)->nodes))->toBe(['save', 'open', 'close'])
        ->and(nodeKeys($file->replace('close', $save)->nodes))->toBe(['open', 'save'])
        ->and(nodeKeys($file->without('open')->nodes))->toBe(['close'])
        ->and(nodeKeys($file->nodes))->toBe(['open', 'close']);
});

it('ignores removing what is not there', function (): void {
    $file = Menu::make('File', 'file', Item::make('Open', id: 'open'));

    expect($file->without('help'))->toEqual($file)
        ->and($file->without('help', 'open')->nodes)->toBe([]);
});

it('refuses to change or place beside what is not there', function (): void {
    $file = Menu::make('File', 'file', Item::make('Open', id: 'open'), Separator::make(), Item::make('Close', id: 'close'));
    $missing = "Menu 'File' has nothing with the key 'save'. It has: open, close.";

    expect(fn () => $file->before('save', Separator::make()))->toThrow(MenuException::class, $missing)
        ->and(fn () => $file->after('save', Separator::make()))->toThrow(MenuException::class, $missing)
        ->and(fn () => $file->replace('save', Separator::make()))->toThrow(MenuException::class, $missing);
});

it('keeps the application menu to the bar, with the id app', function (): void {
    $app = Menu::application(Standard::quit());

    expect($app->application)->toBeTrue()
        ->and($app->id)->toBe('app')
        ->and($app->key())->toBe('app')
        ->and($app->describe())->toBe('the application menu')
        ->and(fn () => Menu::make('File', $app))
        ->toThrow(MenuException::class, "Menu 'File': the application menu belongs to the bar, not inside a menu.");
});

it('changes every definition only by copying it', function (): void {
    $run = fn () => null;
    $item = Item::make('Save', id: 'save');
    $toggle = Toggle::make('Wrap', on: false, id: 'wrap');
    $choice = Choice::make(['a' => 'A', 'b' => 'B'], 'a', id: 'mode');
    $standard = Standard::copy();
    $menu = Menu::make('File', 'file', $item);
    $other = Item::make('Open', id: 'open');
    $bar = MenuBar::make($menu);
    $help = Menu::make('Help', 'help');
    $key = Shortcut::primary('S');

    $changes = [
        [$item, ['onClick' => [$run], 'shortcut' => [$key], 'enabled' => [false], 'label' => ['Store']]],
        [$toggle, ['shortcut' => [$key], 'enabled' => [false], 'label' => ['Wrap lines'], 'on' => [true], 'onToggle' => [$run]]],
        [$choice, ['enabled' => [false], 'chosen' => ['b'], 'onChoose' => [$run]]],
        [$standard, ['onClick' => [$run], 'shortcut' => [$key], 'enabled' => [false], 'label' => ['Duplicate'], 'id' => ['copy']]],
        [$menu, [
            'titled' => ['Datei'], 'enabled' => [false], 'append' => [$other], 'prepend' => [$other],
            'before' => ['save', $other], 'after' => ['save', $other], 'without' => ['save'], 'replace' => ['save', $other],
        ]],
        [$bar, [
            'with' => [$help], 'without' => ['file'], 'before' => ['file', $help], 'after' => ['file', $help],
            'extend' => ['file', fn (Menu $file) => $file->enabled(false)],
            'item' => ['file.save', fn (Item $save) => $save->enabled(false)],
        ]],
        [$key, ['shift' => [], 'alt' => [], 'ctrl' => []]],
    ];

    foreach ($changes as [$original, $methods]) {
        $before = var_export($original, true);
        $public = array_filter(
            (new ReflectionClass($original))->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $method): bool => ! $method->isStatic()
                && ! in_array($method->getName(), ['on', 'changing'], true)
                && in_array((string) $method->getReturnType(), ['self', 'static', $original::class], true),
        );

        expect(array_diff(array_map(fn (ReflectionMethod $method): string => $method->getName(), $public), array_keys($methods)))->toBe([]);

        foreach ($methods as $method => $arguments) {
            $changed = $original->{$method}(...$arguments);

            expect($changed)->toBeInstanceOf($original::class)
                ->and($changed)->not->toBe($original)
                ->and($changed)->not->toEqual($original)
                ->and(var_export($original, true))->toBe($before);
        }
    }
});

it('offers no method the design does not have', function (string $class, string $method): void {
    expect(method_exists($class, $method))->toBeFalse();
})->with([
    [Item::class, 'onMac'], [Item::class, 'offMac'], [Menu::class, 'id'],
    [Surface\Toolkits\Menus::class, 'define'],
]);
