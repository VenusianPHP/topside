<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\Menus\ShortcutKey;
use Surface\Toolkits\Menus\MenuException;
use Surface\Toolkits\Menus\Shortcut;

it('reads Primary as Command on macOS and Control elsewhere', function (): void {
    $save = Shortcut::primary('s')->shift();

    expect($save->key)->toBe(ShortcutKey::S)
        ->and($save->describe())->toBe('Primary+Shift+S')
        ->and($save->describe(true))->toBe('Cmd+Shift+S')
        ->and($save->describe(false))->toBe('Ctrl+Shift+S');
});

it('parses a written shortcut in any case and order', function (string $written, string $described): void {
    expect(Shortcut::of($written)->describe())->toBe($described);
})->with([
    ['Primary+Shift+S', 'Primary+Shift+S'],
    ['shift + primary + s', 'Primary+Shift+S'],
    ['alt+F4', 'Alt+F4'],
    ['F5', 'F5'],
    ['Primary+,', 'Primary+,'],
    ['ctrl+alt+pageup', 'Ctrl+Alt+PageUp'],
]);

it('refuses a shortcut that is not one', function (): void {
    expect(fn () => Shortcut::of('Primary+Shift'))->toThrow(MenuException::class, "Shortcut 'Primary+Shift' has no key.")
        ->and(fn () => Shortcut::of('Primary+S+T'))->toThrow(MenuException::class, "Shortcut 'Primary+S+T' has two keys: 'S' and 'T'.")
        ->and(fn () => Shortcut::of('Hyper+S'))->toThrow(MenuException::class, "Shortcut 'Hyper+S': 'Hyper' is not a modifier (Primary, Shift, Alt, Ctrl) or a shortcut key.")
        ->and(fn () => Shortcut::primary('ß'))->toThrow(MenuException::class, "'ß' is not a shortcut key.");
});

it('lets a key that types nothing stand alone, and then it yields to focus', function (ShortcutKey $key): void {
    expect(Shortcut::key($key)->yieldsToFocus())->toBeTrue()
        ->and(Shortcut::of("Shift+{$key->value}")->yieldsToFocus())->toBeTrue()
        ->and(Shortcut::primary($key)->yieldsToFocus())->toBeFalse();
})->with(array_map(
    fn (string $name): ShortcutKey => ShortcutKey::named($name),
    ['Delete', 'Backspace', 'Up', 'Down', 'Left', 'Right', 'Home', 'End', 'PageUp', 'PageDown', ...array_map(fn (int $n): string => "F{$n}", range(1, 12))],
));

it('needs a modifier on every other key', function (ShortcutKey $key): void {
    $rule = 'needs Primary, Ctrl or Alt: only Delete, Backspace, the arrows, Home, End, PageUp, PageDown and F1 to F12 work alone.';

    expect(fn () => Shortcut::key($key))->toThrow(MenuException::class, $rule)
        ->and(fn () => Shortcut::of("Shift+{$key->value}"))->toThrow(MenuException::class, $rule)
        ->and(Shortcut::of("Alt+{$key->value}")->yieldsToFocus())->toBeFalse();
})->with(array_map(
    fn (string $name): ShortcutKey => ShortcutKey::named($name),
    [...range('A', 'Z'), ...array_map(strval(...), range(0, 9)), '-', '=', '[', ']', '\\', ';', "'", '`', ',', '.', '/', 'Space', 'Tab', 'Enter', 'Escape'],
));

it('compares shortcuts as a platform holds them', function (): void {
    $primary = Shortcut::primary('S');
    $ctrl = Shortcut::of('Ctrl+S');

    expect($primary->same($ctrl, false))->toBeTrue()
        ->and($primary->same($ctrl, true))->toBeFalse()
        ->and($primary->same(Shortcut::primary('S'), true))->toBeTrue()
        ->and($primary->same(Shortcut::primary('S')->shift(), false))->toBeFalse();
});

it('changes a shortcut only by copying it', function (): void {
    $save = Shortcut::primary('S');
    $save->shift()->alt();

    expect($save->shift)->toBeFalse()->and($save->alt)->toBeFalse();
});
