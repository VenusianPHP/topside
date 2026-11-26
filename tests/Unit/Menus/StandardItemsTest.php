<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\Menus\StandardKind;
use Surface\Toolkits\Menus\About;
use Surface\Toolkits\Menus\AboutPanel;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\MenuException;
use Surface\Toolkits\Menus\Separator;
use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\Standard;
use Surface\Toolkits\Menus\StandardItem;

it('gives each standard item its platform label', function (StandardItem $item, string $mac, string $elsewhere): void {
    expect($item->labelOn(true, 'My App'))->toBe($mac)
        ->and($item->labelOn(false, 'My App'))->toBe($elsewhere);
})->with([
    [fn () => Standard::quit(), 'Quit My App', 'Quit'],
    [fn () => Standard::preferences(id: 'settings'), 'Settings…', 'Preferences'],
    [fn () => About::item(id: 'about'), 'About My App', 'About My App'],
    [fn () => Standard::closeWindow(), 'Close Window', 'Close'],
    [fn () => Standard::copy(), 'Copy', 'Copy'],
    [fn () => Standard::pasteAndMatchStyle(), 'Paste and Match Style', 'Paste and Match Style'],
    [fn () => Standard::quit()->label('Leave'), 'Leave', 'Leave'],
]);

it('gives each standard item its platform shortcut', function (StandardItem $item, ?string $mac, ?string $elsewhere): void {
    expect($item->shortcutOn(true)?->describe(true))->toBe($mac)
        ->and($item->shortcutOn(false)?->describe(false))->toBe($elsewhere);
})->with([
    [fn () => Standard::quit(), 'Cmd+Q', 'Ctrl+Q'],
    [fn () => Standard::preferences(id: 'settings'), 'Cmd+,', null],
    [fn () => Standard::fullscreen(), 'Cmd+Ctrl+F', 'F11'],
    [fn () => Standard::minimize(), 'Cmd+M', null],
    [fn () => Standard::redo(), 'Cmd+Shift+Z', 'Ctrl+Shift+Z'],
    [fn () => Standard::selectAll(), 'Cmd+A', 'Ctrl+A'],
    [fn () => Standard::pasteAndMatchStyle(), 'Cmd+Alt+Shift+V', 'Ctrl+Shift+V'],
    [fn () => Standard::showAll(), null, null],
    [fn () => Standard::quit()->shortcut(Shortcut::primary('W')), 'Cmd+W', 'Ctrl+W'],
    [fn () => Standard::quit()->shortcut(null), null, null],
]);

it('has an About only in the two forms the author chooses between', function (): void {
    $panel = About::panel(name: 'My App', version: '11.0', copyright: '© 2026');
    $own = About::item(id: 'about');

    expect($panel->kind)->toBe(StandardKind::ABOUT)
        ->and($panel->panel)->toBeInstanceOf(AboutPanel::class)
        ->and($panel->panel->version)->toBe('11.0')
        ->and($panel->panel->credits)->toBeNull()
        ->and($panel->mailId())->toBeNull()
        ->and($own->panel)->toBeNull()
        ->and($own->mailId())->toBe('about')
        ->and(About::item(onClick: fn () => null)->isReplaced())->toBeTrue()
        ->and(Standard::preferences(onClick: fn () => null)->isReplaced())->toBeTrue()
        ->and((new ReflectionClass(About::class))->getConstructor()->isPrivate())->toBeTrue()
        ->and(fn () => About::panel(''))->toThrow(MenuException::class, "An About panel needs the application's name.")
        ->and(fn () => new StandardItem(StandardKind::QUIT, panel: new AboutPanel('x')))
        ->toThrow(MenuException::class, "Only About takes a panel, not standard item 'quit'.");
});

it('lets mail or a callback replace what a kind does', function (): void {
    $copy = Standard::copy();
    $mail = $copy->id('copy');

    expect($copy->isReplaced())->toBeFalse()
        ->and($copy->mailId())->toBeNull()
        ->and($mail->isReplaced())->toBeTrue()
        ->and($mail->mailId())->toBe('copy')
        ->and($mail->shortcutOn(true)->describe(true))->toBe('Cmd+C')
        ->and($copy->onClick(fn () => null)->isReplaced())->toBeTrue()
        ->and(fn () => $copy->id('Edit Copy'))->toThrow(MenuException::class, 'must be lower case letters');
});

it('says which kinds the toolkit runs by itself', function (): void {
    expect(StandardKind::COPY->isEdit())->toBeTrue()
        ->and(StandardKind::PASTE_AND_MATCH_STYLE->isEdit())->toBeTrue()
        ->and(StandardKind::PASTE_AND_MATCH_STYLE->isNative())->toBeTrue()
        ->and(StandardKind::COPY->isNative())->toBeTrue()
        ->and(StandardKind::MINIMIZE->isWindow())->toBeTrue()
        ->and(StandardKind::HIDE->isMacOnly())->toBeTrue()
        ->and(StandardKind::QUIT->isNative())->toBeFalse()
        ->and(StandardKind::ABOUT->isNative())->toBeFalse()
        ->and(StandardKind::PREFERENCES->isNative())->toBeFalse();
});

it('leaves the macOS-only kinds out everywhere else', function (): void {
    expect(Standard::hide()->shownOn(true))->toBeTrue()
        ->and(Standard::hide()->shownOn(false))->toBeFalse()
        ->and(Standard::hideOthers()->shownOn(false))->toBeFalse()
        ->and(Standard::quit()->shownOn(false))->toBeTrue();
});

it('offers ready-made Edit and Window menus as plain menus', function (): void {
    $edit = Standard::editMenu();
    $window = Standard::windowMenu();

    expect($edit)->toBeInstanceOf(Menu::class)
        ->and($edit->title)->toBe('Edit')
        ->and($edit->id)->toBe('edit')
        ->and(array_map(fn ($n) => $n->key(), $edit->nodes))->toBe(['undo', 'redo', null, 'cut', 'copy', 'paste', 'select-all'])
        ->and($window->title)->toBe('Window')
        ->and($window->id)->toBe('window')
        ->and(array_map(fn ($n) => $n->key(), $window->nodes))->toBe(['minimize', 'fullscreen', 'close-window'])
        ->and($window->titled('Fenster')->id)->toBe('window')
        ->and(array_map(fn ($n) => $n->key(), $edit->without('undo', 'redo')->prepend(Separator::make())->nodes))
        ->toBe([null, null, 'cut', 'copy', 'paste', 'select-all']);
});
