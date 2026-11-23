<?php

declare(strict_types=1);

use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Children;
use Venusian\Topside\Tests\Fakes\Views\Panel;
use Venusian\Topside\Tests\Fakes\Views\Counter;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;
use Venusian\Topside\Tests\Fakes\UI\FakeLabel;

/*
| Components used inside a view: Counter::make(label: 'A') in a render(). Each is built once,
| keeps its own state while it is on screen, takes its props from its parent on every render,
| and mounts what its own render() describes in its place.
*/
beforeEach(fn () => openViewWindow());

it('mounts a component\'s render() in its place, named after its class', function (): void {
    scripted(fn () => Column::make()->with(Counter::make(label: 'A')));

    expect(childNames('column1'))->toBe(['counter1'])
        ->and(childNames('column1.counter1'))->toBe(['label1', 'button2'])
        ->and(at('column1.counter1.label1')->text())->toBe('A: 0');
});

it('keeps each component\'s own state, apart from the others and across its parent\'s renders', function (): void {
    $view = scripted(fn (ScriptedView $v) => Column::make()->with(
        Label::make($v->title),
        Counter::make(label: 'A'),
        Counter::make(label: 'B'),
    ), ['title' => 'one']);
    $before = built();

    click(at('column1.counter2.button2'));
    click(at('column1.counter2.button2'));
    click(at('column1.counter3.button2'));
    $view->title = 'two';
    rerender();

    expect(at('column1.counter2.label1')->text())->toBe('A: 2')
        ->and(at('column1.counter3.label1')->text())->toBe('B: 1')
        ->and(at('column1.label1')->text())->toBe('two')
        ->and(built())->toBe($before);
});

it('gives a component its parent\'s new props on each render', function (): void {
    $view = scripted(fn (ScriptedView $v) => Column::make()->with(Counter::make(label: $v->title)), ['title' => 'Apples']);
    $label = at('column1.counter1.label1');
    click(at('column1.counter1.button2'));
    $label->calls = [];

    $view->title = 'Pears';
    rerender();

    expect($label->calls)->toBe([['applyText', ['Pears: 1']]]);
});

it('puts what it is given where its render() puts Children::slot()', function (): void {
    $view = scripted(fn (ScriptedView $v) => Panel::make(heading: 'Inbox')->with(
        Label::make("{$v->title} unread"),
        Label::make('footer'),
    ), ['title' => '3']);

    expect(childNames('panel1'))->toBe(['label1', 'label2', 'label3', 'button4'])
        ->and(at('panel1.label2')->text())->toBe('3 unread');

    $view->title = '4';
    rerender();

    expect(at('panel1.label2')->text())->toBe('4 unread');
});

it('lets a component tell its parent through a closure prop, and the parent renders again', function (): void {
    scripted(function (ScriptedView $v): Column {
        $open = $v->state['open'] ?? true;

        return Column::make()->with(
            Label::make($open ? 'open' : 'closed'),
            $open ? Panel::make(heading: 'Help', onClose: function () use ($v): void { $v->state['open'] = false; }) : null,
        );
    });
    $panel = at('column1.panel2');

    click(at('column1.panel2.button2'));

    expect(at('column1.label1')->text())->toBe('closed')
        ->and($panel->isRemoved())->toBeTrue()
        ->and(childNames('column1'))->toBe(['label1']);
});

it('starts a component afresh once it was taken off the screen', function (): void {
    $view = scripted(fn (ScriptedView $v) => Column::make()->with(($v->state['shown'] ?? true) ? Counter::make(label: 'A') : null));
    click(at('column1.counter1.button2'));

    $view->state['shown'] = false;
    rerender();
    $view->state['shown'] = true;
    rerender();

    expect(at('column1.counter2.label1')->text())->toBe('A: 0');
});

it('keeps the component and its state when its root becomes another kind', function (): void {
    Panel::$instances = 0;
    $view = scripted(fn (ScriptedView $v) => Column::make()->with(Panel::make(heading: 'H', asLabel: $v->state['label'] ?? false)));
    $column = at('column1.panel1');

    $view->state['label'] = true;
    rerender();

    expect($column->isRemoved())->toBeTrue()
        ->and(at('column1.panel2'))->toBeInstanceOf(FakeLabel::class)
        ->and(at('column1.panel2')->text())->toBe('H');

    $view->state['label'] = false;
    rerender();

    expect(childNames('column1.panel3'))->toBe(['label1', 'button2'])
        ->and(Panel::$instances)->toBe(1);
});

it('moves keyed components with their state', function (): void {
    $view = scripted(fn (ScriptedView $v) => Column::make()->with(
        ...array_map(fn (string $name) => Counter::make(label: $name)->key($name), $v->state['order']),
    ), ['state' => ['order' => ['A', 'B']]]);
    click(at('column1.counter2.button2'));
    $b = at('column1.counter2');

    $view->state['order'] = ['B', 'A'];
    rerender();

    expect(at('column1')->children()[0])->toBe($b)
        ->and(at('column1.counter2.label1')->text())->toBe('B: 1');
});

it('lays settings made where a component is used over its root', function (): void {
    scripted(fn () => Column::make()->with(Counter::make(label: 'A')->hidden()->name('tally')));

    expect(at('column1.tally')->isVisible())->toBeFalse();
});

it('refuses a prop the component does not have', function (): void {
    expect(fn () => scripted(fn () => Column::make()->with(Counter::make(colour: 'red'))))
        ->toThrow(ViewException::class, "has no public property 'colour'");
});

it('refuses Children::slot() outside a component, and as a component\'s root', function (): void {
    expect(fn () => scripted(fn () => Column::make()->with(Children::slot())))
        ->toThrow(ViewException::class, 'is in no component\'s render()');

    expect(fn () => scripted(fn () => Column::make()->with(Panel::make()->with(), SlotOnly::make())))
        ->toThrow(ViewException::class, 'returns Children::slot()');
});

it('leaves nothing behind when a render fails partway', function (): void {
    expect(fn () => scripted(fn () => Column::make()->with(Label::make('built'), Column::make()->with(Children::slot()))))
        ->toThrow(ViewException::class);

    scripted(fn () => Label::make('next'));

    expect(array_map(fn ($c) => $c->name(), screen()->children()))->toBe(['label1'])
        ->and(at('label1')->text())->toBe('next');
});

class SlotOnly extends Surface\Toolkits\Views\Component
{
    public function render(): Surface\Toolkits\Views\Elements\Element
    {
        return Children::slot();
    }
}
