<?php

declare(strict_types=1);

use Surface\Toolkits\View;
use Surface\Toolkits\Presentation;
use Surface\Toolkits\Views\Justify;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Row;
use Surface\Toolkits\Demo\Views\DemoHomeView;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\Mail\Views\ButtonClicked;
use Surface\Core\Actions\Bootstrap\LoadSignals;
use Venusian\Topside\Tests\Fakes\UI\FakeColumn;
use Venusian\Topside\Tests\Fakes\Views\LeavingView;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

beforeEach(fn () => openViewWindow());

describe('the demo home view', function (): void {
    it('puts a card with a label and a button in the middle of the window', function (): void {
        View::render(DemoHomeView::class);

        expect(array_map(fn ($c) => $c->name(), screen()->children()))->toBe(['column1'])
            ->and(childNames('column1'))->toBe(['spacer1', 'card2', 'card3', 'spacer4'])
            ->and(at('column1.card2'))->toBeInstanceOf(FakeColumn::class)
            ->and(childNames('column1.card2'))->toBe(['label1', 'label2', 'button3'])
            ->and(at('column1.card2.label1')->text())->toBe('Venusian Topside')
            ->and(at('column1.card2.label2')->text())->toBe('Not clicked yet')
            ->and(at('column1.card2.button3')->label())->toBe('Click me')
            ->and(at('column1.spacer1')->calls)->toContain(['applyFill', [false, true]])
            ->and(at('column1.spacer4')->calls)->toContain(['applyFill', [false, true]])
            ->and(at('column1.card2')->calls)->toContain(['applyAlign', [Align::CENTER, Align::FILL]])
            ->and(at('column1.card2')->cornerRadius())->toBe(12.0)
            ->and(at('column1')->calls)->toContain(['applyFill', [true, true]]);
    });

    it('reads its check box, switch and dropdown back in its settings card', function (): void {
        View::render(DemoHomeView::class);

        expect(childNames('column1.card3'))->toBe(['label1', 'checkbox2', 'row3', 'row4', 'label5'])
            ->and(at('column1.card3.label5')->text())->toBe('Not subscribed, notifications on, from Venus.');

        at('column1.card3.checkbox2')->nativeToggled(true);
        at('column1.card3.row3.toggle1')->nativeToggled(false);
        at('column1.card3.row4.dropdown2')->nativeChosen(5);
        deliverPosted();

        expect(at('column1.card3.label5')->text())->toBe('Subscribed, notifications off, from Saturn.')
            ->and(at('column1.card3.checkbox2')->isChecked())->toBeTrue();
    });

    it('counts clicks on its label, changing only the label text', function (): void {
        View::render(DemoHomeView::class);
        $label = at('column1.card2.label2');
        $before = built();
        $label->calls = [];

        click(at('column1.card2.button3'));
        click(at('column1.card2.button3'));

        expect(built())->toBe($before)
            ->and(at('column1.card2.label2'))->toBe($label)
            ->and($label->calls)->toBe([['applyText', ['Clicked 1 time']], ['applyText', ['Clicked 2 times']]]);
    });
});

describe('rendering a view', function (): void {
    it('sets props on the view\'s public properties before it renders', function (): void {
        scripted(fn (ScriptedView $v) => Label::make($v->title), ['title' => 'Hello']);

        expect(at('label1')->text())->toBe('Hello');
    });

    it('refuses a prop the view does not have', function (): void {
        expect(fn () => scripted(fn () => Label::make('x'), ['nope' => 1]))
            ->toThrow(ViewException::class, "has no public property 'nope' to set: it has state, title.");
    });

    it('refuses a view that is not a component', function (): void {
        expect(fn () => View::render(stdClass::class))->toThrow(ViewException::class, 'No view [stdClass]');
    });

    it('lets a view root say it does not fill', function (): void {
        scripted(fn () => Column::make()->fill(false, false));

        expect(at('column1')->calls)->not->toContain(['applyFill', [true, true]]);
    });

    it('takes the old view off the screen before the new one goes on', function (): void {
        View::render(DemoHomeView::class);
        $old = at('column1');
        $oldButton = at('column1.card2.button3');

        scripted(fn () => Label::make('Next'));

        expect($old->isRemoved())->toBeTrue()
            ->and($oldButton->isRemoved())->toBeTrue()
            ->and($oldButton->hooks())->toContain('releaseNative')
            ->and(array_map(fn ($c) => $c->name(), screen()->children()))->toBe(['label1']);
    });
});

describe('handlers', function (): void {
    it('gives the handler the mail the primitive posted', function (): void {
        $got = new ArrayObject();
        scripted(fn () => Button::make('Go')->onClick(fn (ButtonClicked $clicked) => $got->append($clicked)));

        click(at('button1'));

        expect($got)->toHaveCount(1)
            ->and($got[0])->toBeInstanceOf(ButtonClicked::class)
            ->and($got[0]->path)->toBe('root.button1');
    });

    it('does not render a view again once its handler put another view on screen', function (): void {
        View::render(LeavingView::class);
        $leaving = Presentation::driver()->view()->component;
        ScriptedView::$describe = fn () => Label::make('Arrived');

        click(at('column1.button1'));

        expect($leaving->renders)->toBe(1)
            ->and(at('label1')->text())->toBe('Arrived');
    });

    it('ignores mail from a primitive with no handler, and from another window', function (): void {
        $runs = new ArrayObject();
        scripted(fn () => Row::make()->with(
            Button::make('Plain'),
            Button::make('Counted')->onClick(fn () => $runs->append('counted')),
        ));
        $counted = at('row1.button2');

        click(at('row1.button1'));
        Presentation::driver()->deliver(new ButtonClicked('another-window', $counted->path(), $counted->uuid()));
        expect($runs)->toHaveCount(0);

        click($counted);
        expect($runs)->toHaveCount(1);
    });

    it('drops the handlers of a view taken off the screen', function (): void {
        $runs = new ArrayObject();
        scripted(fn () => Button::make('Old')->onClick(fn () => $runs->append('old')));
        $old = at('button1');
        $mail = new ButtonClicked(Presentation::driver()->window()->name(), $old->path(), $old->uuid());

        Presentation::driver()->deliver($mail);
        expect($runs)->toHaveCount(1);

        scripted(fn () => Label::make('New'));
        Presentation::driver()->deliver($mail);
        expect($runs)->toHaveCount(1);
    });

    it('reaches the view through signals, as the loop dispatches mail', function (): void {
        (new LoadSignals())->bootstrap($this->app);
        View::render(DemoHomeView::class);

        at('column1.card2.button3')->click();
        foreach ($this->loop->posted as $mail) {
            $this->app['signals']->dispatch($mail);
        }

        expect(at('column1.card2.label2')->text())->toBe('Clicked 1 time');
    });
});

describe('changing what is on screen', function (): void {
    it('sets back a default a render no longer asks for', function (): void {
        $view = scripted(fn (ScriptedView $v) => Label::make('x')->hidden($v->state['hidden'] ?? true));
        $label = at('label1');
        $label->calls = [];

        $view->state['hidden'] = false;
        rerender();

        expect($label->calls)->toBe([['applyVisible', [true, null, 0.0]]]);
    });

    it('changes spacing and padding with setters, on the same container', function (): void {
        $view = scripted(fn (ScriptedView $v) => Column::make(spacing: $v->state['spacing'] ?? 4, padding: $v->state['padding'] ?? 0)->with(Label::make('a')));
        $column = at('column1');

        $view->state['spacing'] = 8;
        rerender();
        expect(at('column1'))->toBe($column)
            ->and($column->calls)->toContain(['applySpacing', [8]]);

        $view->state['padding'] = '40 16';
        rerender();
        expect(at('column1'))->toBe($column)
            ->and($column->isRemoved())->toBeFalse()
            ->and(array_values(array_filter($column->calls, fn (array $call) => $call[0] === 'applyPadding')))->toEqual([['applyPadding', [new Insets(40, 16, 40, 16)]]])
            ->and($column->padding())->toEqual(new Insets(40, 16, 40, 16));

        $calls = count($column->calls);
        rerender();
        expect(count($column->calls))->toBe($calls);
    });

    it('moves keyed children instead of building them again', function (): void {
        $view = scripted(fn (ScriptedView $v) => Column::make()->with(
            ...array_map(fn (string $item) => Label::make($item)->key($item), $v->state['items']),
        ), ['state' => ['items' => ['a', 'b', 'c']]]);
        $a = at('column1.label1');
        $c = at('column1.label3');
        $before = built();

        $view->state['items'] = ['c', 'a'];
        rerender();

        expect(built())->toBe($before)
            ->and(at('column1')->children())->toBe([$c, $a])
            ->and(at('column1')->hooks())->toContain('reorderNative');
    });

    it('builds a child again when its kind changes', function (): void {
        $view = scripted(fn (ScriptedView $v) => Column::make()->with(
            ($v->state['button'] ?? false) ? Button::make('b') : Label::make('l'),
        ));
        $label = at('column1.label1');

        $view->state['button'] = true;
        rerender();

        expect($label->isRemoved())->toBeTrue()
            ->and(childNames('column1'))->toBe(['button2']);
    });

    it('adds and removes children with no key in order', function (): void {
        $view = scripted(fn (ScriptedView $v) => Column::make()->with(
            Label::make('first'),
            ($v->state['middle'] ?? false) ? Button::make('middle') : null,
            Label::make('last'),
        ));
        $first = at('column1.label1');

        $view->state['middle'] = true;
        rerender();
        expect(childNames('column1'))->toBe(['label1', 'button3', 'label2'])
            ->and(at('column1.label1'))->toBe($first);

        $view->state['middle'] = false;
        rerender();
        expect(childNames('column1'))->toBe(['label1', 'label2'])
            ->and(at('column1.label1'))->toBe($first);
    });

    it('adds the justify spacers around children it keeps', function (): void {
        $view = scripted(fn (ScriptedView $v) => Column::make()->justify($v->state['justify'] ?? Justify::START)->with(Label::make('a')));
        $label = at('column1.label1');

        $view->state['justify'] = Justify::CENTER;
        rerender();
        expect(childNames('column1'))->toBe(['spacer2', 'label1', 'spacer3']);

        $view->state['justify'] = Justify::END;
        rerender();
        expect(childNames('column1'))->toBe(['spacer2', 'label1'])
            ->and(at('column1.label1'))->toBe($label);
    });

    it('fills a spacer in a row across, and in a column down', function (): void {
        scripted(fn () => Row::make()->justify(Justify::END)->with(Label::make('a')));

        expect(at('row1.spacer1')->calls)->toContain(['applyFill', [true, false]]);
    });

    it('names a primitive when the view does', function (): void {
        scripted(fn () => Column::make()->name('page')->with(Button::make('Save')->name('save')));

        expect(at('page.save')->path())->toBe('root.page.save');
    });

    it('refuses two siblings with the same key', function (): void {
        expect(fn () => scripted(fn () => Column::make()->with(Label::make('a')->key(1), Label::make('b')->key(1))))
            ->toThrow(ViewException::class, "have the key '1'");
    });
});

it('changes one label among 500 in a few milliseconds', function (): void {
    $view = scripted(fn (ScriptedView $v) => Column::make()->with(
        ...array_map(fn (int $i) => Label::make($i === 250 ? ($v->state['text'] ?? 'x') : "row {$i}")->key($i), range(0, 499)),
    ));

    $started = hrtime(true);
    $view->state['text'] = 'changed';
    rerender();
    $ms = (hrtime(true) - $started) / 1e6;

    fwrite(STDERR, sprintf("\n  re-render and patch of 500 labels: %.2f ms\n", $ms));
    expect(at('column1.label251')->text())->toBe('changed')
        ->and($ms)->toBeLessThan(50.0);
});
