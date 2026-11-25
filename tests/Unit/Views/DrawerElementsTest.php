<?php

declare(strict_types=1);

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\TKTransition;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Overlay;
use Surface\Toolkits\Views\Sharp\SharpCompileError;
use Surface\Contracts\Toolkits\Mail\Views\GroupClicked;
use Surface\Contracts\Toolkits\Mail\Views\ButtonClicked;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

/*
| A container takes a click of its own, and a primitive can move in from an edge by its own
| size: what an off-canvas drawer is made of.
*/
beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});
afterEach(fn () => dropSharpApp());

/** The arguments of each time $hook was told to $primitive, oldest first. */
function drawerToldWith(object $primitive, string $hook): array
{
    return array_values(array_map(static fn (array $call): array => $call[1], array_filter($primitive->calls, static fn (array $call): bool => $call[0] === $hook)));
}

/** The group at $path is clicked the way the user does, and the mail delivered. */
function clickGroup(string $path, float $x = 0.0, float $y = 0.0): void
{
    at($path)->clickAt($x, $y);
    deliverPosted();
}

describe('a clickable group', function (): void {
    it('tells the toolkit to listen, once, and posts GroupClicked where it was clicked', function (): void {
        $column = screen()->column('card');
        $column->setClickable()->setClickable();
        $column->clickAt(12.5, 40.0);

        $mail = test()->loop->posted[0];

        expect(drawerToldWith($column, 'applyClickable'))->toBe([[true]])
            ->and($column->isClickable())->toBeTrue()
            ->and($mail)->toBeInstanceOf(GroupClicked::class)
            ->and($mail->path)->toBe('root.card')
            ->and([$mail->x, $mail->y])->toBe([12.5, 40.0]);
    });

    it('posts nothing when not clickable, disabled, inside a disabled group, or removed', function (): void {
        $outer = screen()->column('outer');
        $card = $outer->column('card');

        $card->clickAt();
        $card->setClickable()->setEnabled(false);
        $card->clickAt();
        $card->setEnabled(true);
        $outer->setEnabled(false);
        $card->clickAt();

        expect(test()->loop->posted)->toBe([]);
    });

    it('stops listening when it is no longer clickable', function (): void {
        $column = screen()->column('card');
        $column->setClickable()->setClickable(false);

        expect(drawerToldWith($column, 'applyClickable'))->toBe([[true], [false]])
            ->and($column->isClickable())->toBeFalse();
    });

    it('refuses once removed', function (): void {
        $column = screen()->column('card');
        $column->remove();

        expect(fn () => $column->setClickable())->toThrow(ToolkitException::class);
    });
});

describe('in a view', function (): void {
    it('runs the container\'s onClick, and only the button\'s for a click on a button in it', function (): void {
        $view = scripted(fn (ScriptedView $v) => Overlay::make()
            ->onClick(function () use ($v): void { $v->state['card'] = ($v->state['card'] ?? 0) + 1; })
            ->with(
                Label::make('Adventure Awaits'),
                Button::make('Share')->onClick(function () use ($v): void { $v->state['share'] = ($v->state['share'] ?? 0) + 1; }),
            ));

        clickGroup('overlay1');
        click(at('overlay1.button2'));

        expect($view->state)->toBe(['card' => 1, 'share' => 1])
            ->and(at('overlay1')->isClickable())->toBeTrue();
    });

    it('listens only where something wants the click', function (): void {
        scripted(fn () => Column::make()->with(Label::make('x')));

        expect(at('column1')->isClickable())->toBeFalse()
            ->and(drawerToldWith(at('column1'), 'applyClickable'))->toBe([]);
    });
});

describe('a move', function (): void {
    it('starts one own size away from its edge, with no fade and no fixed distance', function (TKTransition $move, array $edge): void {
        expect($move->moves())->toBeTrue()
            ->and($move->edge())->toBe($edge)
            ->and($move->offset())->toBe([0, 0]);
    })->with([
        'right' => [TKTransition::MOVE_RIGHT, [1, 0]],
        'left' => [TKTransition::MOVE_LEFT, [-1, 0]],
        'top' => [TKTransition::MOVE_TOP, [0, -1]],
        'bottom' => [TKTransition::MOVE_BOTTOM, [0, 1]],
    ]);

    it('follows easeOutQuint in and its mirror, easeInQuint, out', function (): void {
        $in = TKTransition::MOVE_RIGHT->curve(true);
        $out = TKTransition::MOVE_RIGHT->curve(false);

        expect($in)->toBe([0.22, 1.0, 0.36, 1.0])
            ->and($out)->toBe([1 - $in[2], 1 - $in[3], 1 - $in[0], 1 - $in[1]])
            ->and(TKTransition::FADE->curve(true))->toBeNull()
            ->and(TKTransition::SLIDE_LEFT->curve(false))->toBeNull();
    });

    it('leaves fades and slides as they were', function (): void {
        expect(TKTransition::FADE->moves())->toBeFalse()
            ->and(TKTransition::SLIDE_LEFT->edge())->toBeNull()
            ->and(TKTransition::SLIDE_LEFT->offset())->toBe([TKTransition::SLIDE_DISTANCE, 0]);
    });

    it('runs for the default time unless given one', function (): void {
        $column = screen()->column('drawer');
        $column->appear(TKTransition::MOVE_RIGHT);
        $column->setVisible(false);
        $column->setVisible(true);

        expect(drawerToldWith($column, 'applyVisible'))->toBe([
            [false, null, 0.0],
            [true, TKTransition::MOVE_RIGHT, TKTransition::DEFAULT_SECONDS],
        ]);
    });
});

describe('transitions in a view', function (): void {
    it('hides a drawer built hidden at once, then plays its move each time it opens and closes', function (): void {
        $view = scripted(fn (ScriptedView $v) => Column::make()
            ->visible($v->state['open'] ?? false)
            ->appear(TKTransition::MOVE_RIGHT)
            ->disappear(TKTransition::MOVE_RIGHT, 0.4)
            ->with(Label::make('story')));
        $drawer = at('column1');

        $view->state = ['open' => true];
        rerender();
        $view->state = ['open' => false];
        rerender();

        expect(drawerToldWith($drawer, 'applyVisible'))->toBe([
            [false, null, 0.0],
            [true, TKTransition::MOVE_RIGHT, TKTransition::DEFAULT_SECONDS],
            [false, TKTransition::MOVE_RIGHT, 0.4],
        ]);
    });

    it('plays nothing again on a render that changes something else', function (): void {
        $view = scripted(fn (ScriptedView $v) => Column::make()
            ->appear(TKTransition::MOVE_RIGHT)
            ->with(Label::make($v->state['title'] ?? 'a')));
        $drawer = at('column1');

        $view->state = ['title' => 'b'];
        rerender();

        expect(drawerToldWith($drawer, 'applyVisible'))->toBe([]);
    });
});

describe('in Sharp', function (): void {
    it('reads appear and disappear with or without a time, and @click on a container', function (): void {
        sharp(<<<'S'
            <Overlay>
                <Column @click="open"><Label>card</Label></Column>
                <Column :visible="$open" appear="move-right" disappear="move-right 0.4"><Label>drawer</Label></Column>
            </Overlay>
            S, <<<'S'
            public bool $open = false;

            public function open(): void
            {
                $this->open = true;
            }
            S);

        clickGroup('overlay1.column1');

        expect(at('overlay1.column2')->isVisible())->toBeTrue()
            ->and(drawerToldWith(at('overlay1.column2'), 'applyVisible'))->toBe([
                [false, null, 0.0],
                [true, TKTransition::MOVE_RIGHT, TKTransition::DEFAULT_SECONDS],
            ]);
    });

    it('says what is wrong at compile time', function (string $markup, string $says): void {
        expect(fn () => sharp($markup))->toThrow(SharpCompileError::class, $says);
    })->with([
        'an unknown transition' => ['<Column appear="swoosh" />', 'swoosh'],
        'a click on a label' => ['<Label @click="go">x</Label>', 'has no @click'],
    ]);
});
