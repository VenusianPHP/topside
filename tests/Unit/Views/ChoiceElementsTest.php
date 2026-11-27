<?php

declare(strict_types=1);

use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Toggle;
use Surface\Toolkits\Views\Elements\Checkbox;
use Surface\Toolkits\Views\Elements\Dropdown;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;
use Venusian\Topside\Tests\Fakes\UI\FakeToggle;
use Venusian\Topside\Tests\Fakes\UI\FakeCheckbox;
use Venusian\Topside\Tests\Fakes\UI\FakeDropdown;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

/*
| Check box, switch and dropdown: what the primitives hold and post, the same on every
| toolkit, and the elements that put them in a view. A fake native signals a change made
| from code too, as GTK and Qt do, so "posts nothing for a change from code" is checked.
*/
beforeEach(fn () => openViewWindow());

/** The mail the window posted since the last call, taken out. */
function takePosted(): array
{
    [$posted, test()->loop->posted] = [test()->loop->posted, []];

    return $posted;
}

describe('the primitives', function (): void {
    it('posts Toggled when the user checks a box, and nothing when code does', function (): void {
        $box = screen()->checkbox('agree', 'I agree');

        $box->setChecked(true);
        expect(takePosted())->toBe([])->and($box->isChecked())->toBeTrue();

        $box->nativeToggled(false);
        $mail = takePosted();

        expect($mail)->toHaveCount(1)
            ->and($mail[0])->toBeInstanceOf(Toggled::class)
            ->and($mail[0]->on)->toBeFalse()
            ->and($mail[0]->name())->toBe('view.toggled.topside-tests.root.agree')
            ->and($box->isChecked())->toBeFalse();
    });

    it('builds a check box with its label and state', function (): void {
        $box = screen()->checkbox('agree', 'I agree', true);

        expect($box)->toBeInstanceOf(FakeCheckbox::class)
            ->and($box->label())->toBe('I agree')
            ->and($box->isChecked())->toBeTrue()
            ->and($box->setLabel('Yes')->label())->toBe('Yes');
    });

    it('posts nothing from a disabled check box, or one inside a disabled container', function (): void {
        $row = screen()->row('row');
        $box = $row->checkbox('agree', 'I agree');

        $box->disable();
        $box->nativeToggled(true);
        $box->enable();
        $row->disable();
        $box->nativeToggled(true);

        expect(takePosted())->toBe([])->and($box->isChecked())->toBeFalse();
    });

    it('posts Toggled when the user flips a switch, and nothing when code does', function (): void {
        $switch = screen()->toggle('dark', true);
        expect($switch)->toBeInstanceOf(FakeToggle::class)->and($switch->isOn())->toBeTrue();

        $switch->setOn(false);
        expect(takePosted())->toBe([]);

        $switch->nativeToggled(true);
        $mail = takePosted();
        expect($mail[0])->toBeInstanceOf(Toggled::class)->and($mail[0]->on)->toBeTrue();
    });

    it('posts SelectionChanged when the user chooses, and nothing when code selects', function (): void {
        $menu = screen()->dropdown('size', ['S', 'M', 'L'], 1);
        expect($menu)->toBeInstanceOf(FakeDropdown::class)
            ->and($menu->selected())->toBe(1)
            ->and($menu->selectedOption())->toBe('M');

        $menu->select(2);
        expect(takePosted())->toBe([]);

        $menu->nativeChosen(0);
        $mail = takePosted();

        expect($mail)->toHaveCount(1)
            ->and($mail[0])->toBeInstanceOf(SelectionChanged::class)
            ->and($mail[0]->index)->toBe(0)
            ->and($mail[0]->option)->toBe('S')
            ->and($mail[0]->name())->toBe('view.selection-changed.topside-tests.root.size');
    });

    it('keeps a dropdown\'s selection while in range when its options change', function (): void {
        $menu = screen()->dropdown('size', ['S', 'M', 'L'], 2);

        $menu->setOptions(['A', 'B', 'C', 'D']);
        expect($menu->selected())->toBe(2);

        $menu->setOptions(['only']);
        expect($menu->selected())->toBe(0)
            ->and($menu->calls)->toContain(['applyOptions', [['only'], 0]]);

        $menu->setOptions([]);
        expect($menu->selected())->toBe(-1)->and($menu->selectedOption())->toBeNull();
    });

    it('refuses a dropdown selection out of range, and options that are not strings', function (): void {
        $menu = screen()->dropdown('size', ['S']);

        expect(fn () => $menu->select(3))->toThrow(ToolkitException::class, 'Option 3 is out of range')
            ->and(fn () => screen()->dropdown('bad', ['S'], 5))->toThrow(ToolkitException::class, "out of range for 'bad'")
            ->and(fn () => screen()->dropdown('worse', [1, 2]))->toThrow(ToolkitException::class, 'list of strings')
            ->and(screen()->view('bad'))->toBeNull();
    });

    it('ignores a native choice out of range', function (): void {
        $menu = screen()->dropdown('size', ['S', 'M']);

        $menu->nativeChosen(7);

        expect(takePosted())->toBe([])->and($menu->selected())->toBe(0);
    });

    it('holds an icon for each option, the file before the symbol, and none past the list', function (): void {
        $menu = screen()->dropdown('kind', ['All', 'Images', 'Audio']);
        $menu->setIcons([['symbol' => 'magnifyingglass'], null, ['file' => '/a/audio.png', 'symbol' => '']]);

        expect($menu->icons())->toBe([['file' => null, 'symbol' => 'magnifyingglass'], null, ['file' => '/a/audio.png', 'symbol' => null]])
            ->and($menu->calls)->toContain(['applyIcons', [[['file' => null, 'symbol' => 'magnifyingglass'], null, ['file' => '/a/audio.png', 'symbol' => null]]]]);

        $menu->setIcons([['symbol' => 'magnifyingglass']]);
        expect($menu->calls[array_key_last($menu->calls)])->toBe(['applyIcons', [[['file' => null, 'symbol' => 'magnifyingglass'], null, null]]]);
    });

    it('gives rebuilt options their icons again', function (): void {
        $menu = screen()->dropdown('kind', ['All', 'Images']);
        $menu->setIcons([['symbol' => 'magnifyingglass'], ['symbol' => 'photo']]);
        $menu->calls = [];

        $menu->setOptions(['All', 'Images', 'Videos']);

        expect($menu->hooks())->toBe(['applyOptions', 'applyIcons'])
            ->and($menu->calls[1])->toBe(['applyIcons', [[['file' => null, 'symbol' => 'magnifyingglass'], ['file' => null, 'symbol' => 'photo'], null]]]);
    });

    it('refuses icons that are not a list of file and symbol entries', function (): void {
        $menu = screen()->dropdown('kind', ['All']);

        expect(fn () => $menu->setIcons(['a' => null]))->toThrow(ToolkitException::class, 'a list, one for each option')
            ->and(fn () => $menu->setIcons(['photo']))->toThrow(ToolkitException::class, "null or ['file' => ?string, 'symbol' => ?string]")
            ->and(fn () => $menu->setIcons([['name' => 'photo']]))->toThrow(ToolkitException::class, "for 'root.kind'")
            ->and(fn () => $menu->setIcons([['symbol' => 7]]))->toThrow(ToolkitException::class, "null or ['file'");
    });

    it('tells the native once when it turns icon-only, and once when it turns back', function (): void {
        $menu = screen()->dropdown('kind', ['All']);

        $menu->setIconOnly();
        $menu->setIconOnly();
        $menu->setIconOnly(false);

        expect($menu->isIconOnly())->toBeFalse()
            ->and(array_values(array_filter($menu->calls, fn (array $c): bool => $c[0] === 'applyIconOnly')))->toBe([['applyIconOnly', [true]], ['applyIconOnly', [false]]]);
    });
});

describe('the elements', function (): void {
    it('gives onToggle the new state, and the view renders it', function (): void {
        $view = scripted(fn (ScriptedView $v) => Column::make()->with(
            Checkbox::make($v->state['agree'] ?? false ? 'Agreed' : 'Agree?', checked: $v->state['agree'] ?? false)
                ->onToggle(function (bool $on) use ($v): void { $v->state['agree'] = $on; }),
        ));
        $box = at('column1.checkbox1');

        $box->nativeToggled(true);
        deliverPosted();

        expect($view->state['agree'])->toBeTrue()
            ->and($box->label())->toBe('Agreed')
            ->and($box->isChecked())->toBeTrue()
            ->and(at('column1.checkbox1'))->toBe($box);
    });

    it('gives a switch\'s onToggle the new state', function (): void {
        $view = scripted(fn (ScriptedView $v) => Toggle::make(on: $v->state['on'] ?? false)
            ->onToggle(function (bool $on) use ($v): void { $v->state['on'] = $on; }));

        at('toggle1')->nativeToggled(true);
        deliverPosted();

        expect($view->state['on'])->toBeTrue();
    });

    it('sets the state a render asks for, posting nothing', function (): void {
        $view = scripted(fn (ScriptedView $v) => Toggle::make(on: $v->state['on'] ?? false));

        $view->state['on'] = true;
        rerender();

        expect(at('toggle1')->isOn())->toBeTrue()->and(takePosted())->toBe([]);
    });

    it('gives onChange the index and the option, and keeps the dropdown in step', function (): void {
        $sizes = ['Small', 'Medium', 'Large'];
        $view = scripted(fn (ScriptedView $v) => Dropdown::make($sizes, selected: $v->state['size'] ?? 0)
            ->onChange(function (int $index, string $option) use ($v): void { $v->state['size'] = $index; $v->title = $option; }));

        at('dropdown1')->nativeChosen(2);
        deliverPosted();

        expect($view->state['size'])->toBe(2)
            ->and($view->title)->toBe('Large')
            ->and(at('dropdown1')->selected())->toBe(2);
    });

    it('sets new options, then the selection, against what the dropdown holds', function (): void {
        $view = scripted(fn (ScriptedView $v) => Dropdown::make($v->state['options'], selected: $v->state['selected']), ['state' => ['options' => ['A', 'B', 'C'], 'selected' => 2]]);
        $menu = at('dropdown1');

        $view->state = ['options' => ['X', 'Y'], 'selected' => 1];
        rerender();
        expect($menu->options())->toBe(['X', 'Y'])->and($menu->selected())->toBe(1);

        $view->state = ['options' => ['X', 'Y', 'Z'], 'selected' => 1];
        $menu->calls = [];
        rerender();
        expect($menu->hooks())->toBe(['applyOptions']);
    });

    it('gives the dropdown its icons and makes it icon-only, and drops both when a render leaves them out', function (): void {
        $view = scripted(fn (ScriptedView $v) => ($v->state['icons'] ?? true)
            ? Dropdown::make(['All', 'Images'])->icons([['symbol' => 'magnifyingglass'], ['file' => 'media/image.png']])->iconOnly()
            : Dropdown::make(['All', 'Images']));
        $menu = at('dropdown1');

        expect($menu->isIconOnly())->toBeTrue()
            ->and($menu->icons())->toBe([['file' => null, 'symbol' => 'magnifyingglass'], ['file' => app()->basePath('resources/media/image.png'), 'symbol' => null]]);

        $view->state['icons'] = false;
        rerender();

        expect($menu->isIconOnly())->toBeFalse()->and($menu->icons())->toBe([]);
    });
});
