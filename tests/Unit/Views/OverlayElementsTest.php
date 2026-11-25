<?php

declare(strict_types=1);

use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\Corners;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Image;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Overlay;
use Surface\Toolkits\Views\Sharp\SharpCompileError;
use Venusian\Topside\Tests\Fakes\UI\FakeOverlay;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

/*
| An overlay holds its children on top of each other; any primitive can round its corners
| apart and clip what is inside to them.
*/
beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});
afterEach(fn () => dropSharpApp());

/** The arguments of each time $hook was told to $primitive, oldest first. */
function overlayToldWith(object $primitive, string $hook): array
{
    return array_values(array_map(static fn (array $call): array => $call[1], array_filter($primitive->calls, static fn (array $call): bool => $call[0] === $hook)));
}

describe('corners', function (): void {
    it('reads one to four radii as CSS border-radius does', function (mixed $written, array $radii): void {
        expect(Corners::of($written)->toArray())->toBe($radii);
    })->with([
        'an int' => [12, [12.0, 12.0, 12.0, 12.0]],
        'a float' => [2.5, [2.5, 2.5, 2.5, 2.5]],
        'one' => ['12', [12.0, 12.0, 12.0, 12.0]],
        'two' => ['12 0', [12.0, 0.0, 12.0, 0.0]],
        'three' => ['12 0 4', [12.0, 0.0, 4.0, 0.0]],
        'four' => ['0 0 14 14', [0.0, 0.0, 14.0, 14.0]],
        'an array' => [[1, 2, 3.5, 4], [1.0, 2.0, 3.5, 4.0]],
    ]);

    it('writes itself back the shortest way', function (string $written): void {
        expect((string) Corners::of($written))->toBe($written);
    })->with(['12', '12 0', '12 0 4', '0 0 14 14', '0', '2.5']);

    it('knows its largest radius and whether all four are the same', function (): void {
        $hero = Corners::of('0 0 14 14');

        expect($hero->largest())->toBe(14.0)
            ->and($hero->isUniform())->toBeFalse()
            ->and(Corners::of(8)->isUniform())->toBeTrue()
            ->and(Corners::none()->isZero())->toBeTrue();
    });

    it('refuses what CSS would not take', function (mixed $written, string $says): void {
        expect(fn () => Corners::of($written))->toThrow(InvalidArgumentException::class, $says);
    })->with([
        'five' => ['1 2 3 4 5', 'one to four'],
        'a negative' => ['0 -1', '0 or more'],
        'a unit' => ['12px', 'one to four radii'],
        'empty' => ['', 'one to four radii'],
    ]);
});

describe('a primitive', function (): void {
    it('gives a toolkit with one radius the radius when the corners agree', function (): void {
        $column = screen()->column('c');
        $column->setCornerRadius(12);

        expect($column->corners())->toEqual(Corners::of(12))
            ->and($column->cornerRadius())->toBe(12.0)
            ->and(overlayToldWith($column, 'applyCornerRadius'))->toBe([[12.0]]);
    });

    it('gives it square corners when they differ, since rounding all four would be wrong', function (): void {
        $column = screen()->column('c');
        $column->setCornerRadius('0 0 14 14');

        expect($column->corners())->toEqual(new Corners(0.0, 0.0, 14.0, 14.0))
            ->and($column->cornerRadius())->toBe(14.0)
            ->and(overlayToldWith($column, 'applyCornerRadius'))->toBe([[0.0]]);
    });

    it('refuses a negative corner and keeps what it had', function (): void {
        $column = screen()->column('c');
        $column->setCornerRadius(4);

        expect(fn () => $column->setCornerRadius('4 -1'))->toThrow(ToolkitException::class, "Corner radius for 'root.c'")
            ->and($column->corners())->toEqual(Corners::of(4));
    });

    it('clips only when told, and says so to the toolkit', function (): void {
        $column = screen()->column('c');

        expect($column->clips())->toBeFalse();

        $column->setClip();
        $column->setClip(false);

        expect($column->clips())->toBeFalse()
            ->and(overlayToldWith($column, 'applyClip'))->toBe([[true], [false]]);
    });
});

describe('the overlay primitive', function (): void {
    it('keeps its children in order from the back, and moves them', function (): void {
        $overlay = screen()->overlay('o');
        $back = $overlay->image('back');
        $overlay->label('front', 'hi');

        $back->moveTo(1);

        expect(array_map(fn ($c) => $c->name(), $overlay->children()))->toBe(['front', 'back'])
            ->and(overlayToldWith($overlay, 'reorderNative'))->toBe([['back', 1]]);
    });

    it('is sized by every child but one that fills both ways', function (): void {
        /** @var FakeOverlay $overlay */
        $overlay = screen()->overlay('o');
        $overlay->image('picture')->fill();
        $overlay->column('wide')->fill(true, false);
        $overlay->label('text', 'hi')->align(Align::START, Align::START);

        expect($overlay->sizingChildren())->toBe(['wide', 'text']);
    });

    it('takes no spare room for a child that fills both ways, only for one that sizes it', function (): void {
        /** @var FakeOverlay $overlay */
        $overlay = screen()->overlay('o');
        $overlay->image('picture')->fill();

        expect([$overlay->expandsAlong(true), $overlay->expandsAlong(false)])->toBe([false, false]);

        $overlay->column('wide')->fill(true, false);

        expect([$overlay->expandsAlong(true), $overlay->expandsAlong(false)])->toBe([true, false]);

        $overlay->fill(false, true);

        expect($overlay->expandsAlong(false))->toBeTrue();
    });

    it('takes padding as CSS does and changes it live', function (): void {
        $overlay = screen()->overlay('o', Insets::of('8 16'));

        $overlay->setPadding(4);

        expect($overlay->padding())->toEqual(Insets::of(4))
            ->and(overlayToldWith($overlay, 'applyPadding'))->toEqual([[Insets::of(4)]])
            ->and(fn () => $overlay->setPadding('1 -2'))->toThrow(ToolkitException::class, "Padding for 'root.o'");
    });
});

describe('in a view', function (): void {
    it('builds an overlay with its children in order', function (): void {
        scripted(fn () => Overlay::make('8')->with(
            Image::make()->fill(),
            Column::make(4)->align(Align::START, Align::START)->with(Label::make('Adventure Awaits')),
        ));

        expect(childNames('overlay1'))->toBe(['image1', 'column2'])
            ->and(at('overlay1')->padding())->toEqual(Insets::of(8))
            ->and(at('overlay1.column2.label1')->text())->toBe('Adventure Awaits');
    });

    it('changes corners, clip and padding in place, never building the overlay again', function (): void {
        $view = scripted(fn (ScriptedView $v) => Overlay::make($v->state['padding'] ?? 0)
            ->cornerRadius($v->state['corners'] ?? 0)
            ->clip($v->state['clip'] ?? false)
            ->with(Label::make('x')));
        $overlay = at('overlay1');

        $view->state = ['padding' => '0 24', 'corners' => '0 0 14 14', 'clip' => true];
        rerender();

        expect(at('overlay1'))->toBe($overlay)
            ->and($overlay->padding())->toEqual(Insets::of('0 24'))
            ->and($overlay->corners())->toEqual(Corners::of('0 0 14 14'))
            ->and($overlay->clips())->toBeTrue();

        $view->state = [];
        rerender();

        expect($overlay->corners())->toEqual(Corners::none())
            ->and($overlay->clips())->toBeFalse();
    });

    it('refuses a corner radius it cannot read', function (): void {
        expect(fn () => Column::make()->cornerRadius('1 2 3 4 5'))->toThrow(ViewException::class, 'A corner radius');
    });
});

describe('in Sharp', function (): void {
    it('reads an overlay with clip and per-corner radius', function (): void {
        sharp(<<<'S'
            <Overlay padding="0 24" cornerRadius="0 0 14 14" clip minSize="0 320">
                <Image scaling="fill" cornerRadius="0 0 14 14" fill />
                <Column padding="28 32" align="start start">
                    <Label>Adventure Awaits</Label>
                </Column>
            </Overlay>
            S);

        $overlay = at('overlay1');

        expect($overlay)->toBeInstanceOf(FakeOverlay::class)
            ->and($overlay->padding())->toEqual(Insets::of('0 24'))
            ->and($overlay->corners())->toEqual(Corners::of('0 0 14 14'))
            ->and($overlay->clips())->toBeTrue()
            ->and(at('overlay1.image1')->corners())->toEqual(Corners::of('0 0 14 14'))
            ->and($overlay->sizingChildren())->toBe(['column2']);
    });

    it('takes one radius as a number and four as PHP', function (): void {
        sharp('<Column cornerRadius="12"><Row :cornerRadius="[0, 0, 8, 8]" /></Column>');

        expect(at('column1')->corners())->toEqual(Corners::of(12))
            ->and(at('column1.row1')->corners())->toEqual(Corners::of('0 0 8 8'));
    });

    it('says what is wrong at compile time', function (string $markup, string $says): void {
        expect(fn () => sharp($markup))->toThrow(SharpCompileError::class, $says);
    })->with([
        'five corners' => ['<Column cornerRadius="1 2 3 4 5" />', 'one to four radii'],
        'a unit' => ['<Column cornerRadius="12px" />', 'one to four radii'],
    ]);
});
