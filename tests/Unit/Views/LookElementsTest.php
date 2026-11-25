<?php

declare(strict_types=1);

use Surface\NutsAndBolts\Color;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\Cursor;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\ButtonRole;
use Surface\Contracts\Toolkits\UI\ButtonStyle;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\FontWeight;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Toolkits\Views\Elements\Row;
use Surface\Toolkits\Views\Elements\Grid;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\TextInput;
use Surface\Toolkits\Views\Sharp\SharpCompileError;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});
afterEach(fn () => dropSharpApp());

/** The hooks a primitive was told after it was built, with their arguments. */
function told(object $primitive): array
{
    return array_values(array_filter($primitive->calls, static fn (array $call): bool => $call[0] !== 'bootstrapNative'));
}

/** The arguments of each time $hook was told to $primitive, oldest first. */
function toldWith(object $primitive, string $hook): array
{
    return array_values(array_map(static fn (array $call): array => $call[1], array_filter($primitive->calls, static fn (array $call): bool => $call[0] === $hook)));
}

describe('insets', function (): void {
    it('reads one to four values as CSS does', function (mixed $written, array $sides): void {
        $insets = Insets::of($written);

        expect([$insets->top, $insets->right, $insets->bottom, $insets->left])->toBe($sides);
    })->with([
        'an int' => [16, [16, 16, 16, 16]],
        'one' => ['16', [16, 16, 16, 16]],
        'two' => ['40 16', [40, 16, 40, 16]],
        'three' => ['40 16 8', [40, 16, 8, 16]],
        'four' => ['40 16 8 0', [40, 16, 8, 0]],
        'spaced out' => ["  40\t16 ", [40, 16, 40, 16]],
        'an array' => [[1, 2, 3, 4], [1, 2, 3, 4]],
    ]);

    it('writes itself back the shortest way', function (string $written): void {
        expect((string) Insets::of($written))->toBe($written);
    })->with(['16', '40 16', '40 16 8', '40 16 8 0', '0']);

    it('refuses what CSS would not take', function (mixed $written, string $says): void {
        expect(fn () => Insets::of($written))->toThrow(InvalidArgumentException::class, $says);
    })->with([
        'five' => ['1 2 3 4 5', 'one to four'],
        'none' => ['', 'one to four'],
        'a unit' => ['16px', 'whole numbers'],
        'a negative' => ['-1', '0 or more'],
        'a fraction' => ['1.5', 'whole numbers'],
    ]);
});

describe('padding', function (): void {
    it('builds a container with each side its own, then changes it live', function (): void {
        $column = screen()->column('c', 4, Insets::of('40 16'));

        expect($column->padding())->toEqual(new Insets(40, 16, 40, 16));

        $column->setPadding('1 2 3 4');
        expect($column->padding())->toEqual(new Insets(1, 2, 3, 4))
            ->and(told($column))->toEqual([['applyPadding', [new Insets(1, 2, 3, 4)]]]);
    });

    it('takes an int for every side, as before', function (): void {
        expect(screen()->row('r', 0, 12)->padding())->toEqual(new Insets(12, 12, 12, 12))
            ->and(screen()->grid('g', 0, 3)->padding())->toEqual(new Insets(3, 3, 3, 3));
    });

    it('refuses a negative side, and changes nothing', function (): void {
        $column = screen()->column('c', 0, 8);

        expect(fn () => $column->setPadding('8 -1'))->toThrow(ToolkitException::class, "padding for 'root.c'")
            ->and($column->padding())->toEqual(Insets::of(8))
            ->and(fn () => screen()->column('d', 0, -1))->toThrow(ToolkitException::class, '0 or more');
    });

    it('goes through setPadding on a grid and a tab page too, never a rebuild', function (): void {
        $view = scripted(fn (ScriptedView $v) => Grid::make(2, 0, $v->state['padding'] ?? 0)->with(Label::make('a')));
        $grid = at('grid1');

        $view->state['padding'] = '0 0 8';
        rerender();

        expect(at('grid1'))->toBe($grid)
            ->and(toldWith($grid, 'applyPadding'))->toEqual([[new Insets(0, 0, 8, 0)]]);
    });
});

describe('a margin, cursor, tooltip and border on any primitive', function (): void {
    it('tells the container first, then the primitive', function (): void {
        $label = screen()->label('l', 'hi');
        screen()->calls = [];

        $label->setMargin('12 0');

        expect($label->margin())->toEqual(new Insets(12, 0, 12, 0))
            ->and(told($label))->toEqual([['applyMargin', [new Insets(12, 0, 12, 0)]]])
            ->and(screen()->calls)->toBe([['childLayoutChanged', ['l']]]);
    });

    it('answers what was set, and hands it to the hook', function (): void {
        $label = screen()->label('l', 'hi');

        $label->setMargin(4)->setCursor(Cursor::TEXT)->setTooltip('Say hi')->setBorder(1, Color::hex('#336699'));

        expect($label->margin())->toEqual(Insets::of(4))
            ->and($label->cursor())->toBe(Cursor::TEXT)
            ->and($label->tooltip())->toBe('Say hi')
            ->and($label->borderWidth())->toBe(1)
            ->and($label->borderColor())->toEqual(Color::hex('#336699'))
            ->and(told($label))->toEqual([
                ['applyMargin', [Insets::of(4)]],
                ['applyCursor', [Cursor::TEXT]],
                ['applyTooltip', ['Say hi']],
                ['applyBorder', [1, Color::hex('#336699')]],
            ]);
    });

    it('takes an empty tooltip as none, and refuses a negative border or margin', function (): void {
        $label = screen()->label('l', 'hi');

        $label->setTooltip('');
        expect($label->tooltip())->toBeNull()
            ->and(fn () => $label->setBorder(-1))->toThrow(ToolkitException::class, '0 or more wide')
            ->and(fn () => $label->setMargin('1 -1'))->toThrow(ToolkitException::class, "margin for 'root.l'");
    });

    it('starts with none of them', function (): void {
        $label = screen()->label('l', 'hi');

        expect($label->margin())->toEqual(Insets::none())
            ->and($label->cursor())->toBeNull()
            ->and($label->tooltip())->toBeNull()
            ->and($label->borderWidth())->toBe(0)
            ->and($label->borderColor())->toBeNull();
    });
});

describe('the button', function (): void {
    it('shows a pointing hand in link style unless a cursor was set', function (): void {
        $button = screen()->button('b', 'Go');

        $button->setStyle(ButtonStyle::LINK);
        expect($button->style())->toBe(ButtonStyle::LINK)
            ->and(told($button))->toBe([['applyStyle', [ButtonStyle::LINK]], ['applyCursor', [Cursor::POINTER]]]);

        $button->calls = [];
        $button->setCursor(Cursor::CROSSHAIR);
        $button->setCursor(null);
        $button->setStyle(ButtonStyle::BORDERLESS);
        expect($button->hooks())->toBe(['applyCursor', 'applyCursor', 'applyStyle', 'applyCursor'])
            ->and(array_column($button->calls, 1))->toBe([[Cursor::CROSSHAIR], [Cursor::POINTER], [ButtonStyle::BORDERLESS], [null]]);
    });

    it('hands the icon file and the symbol over together', function (): void {
        $button = screen()->button('b', 'Go');

        $button->setSymbol('star');
        $button->setIcon('/tmp/star.png');
        $button->setIcon(null);

        expect($button->icon())->toBeNull()
            ->and($button->symbol())->toBe('star')
            ->and(array_column(told($button), 1))->toBe([[null, 'star'], ['/tmp/star.png', 'star'], [null, 'star']]);
    });

    it('takes a font, a colour, an alignment and a role', function (): void {
        $button = screen()->button('b', 'Go');
        $font = new FontSpec(13.0, weight: FontWeight::SEMIBOLD);

        $button->setFont($font)->setTextColor(Color::hex('#ff0000'))->setAlignment(TextAlignment::LEFT)->setRole(ButtonRole::DEFAULT);

        expect($button->role())->toBe(ButtonRole::DEFAULT)
            ->and($button->hooks())->toBe(['bootstrapNative', 'applyFont', 'applyTextColor', 'applyAlignment', 'applyRole']);
    });
});

describe('the text input', function (): void {
    it('is built as a search field, never also secret', function (): void {
        expect(screen()->textInput('s', search: true)->isSearch())->toBeTrue()
            ->and(screen()->textInput('t')->isSearch())->toBeFalse()
            ->and(fn () => screen()->textInput('u', secret: true, search: true))->toThrow(ToolkitException::class, 'not both')
            ->and(fn () => TextInput::make(secret: true, search: true))->toThrow(ViewException::class, 'not both');
    });

    it('answers the text a maximum length cut, and posts nothing for it', function (): void {
        $input = screen()->textInput('t', 'abcdefgh');
        test()->loop->posted = [];

        $input->setMaxLength(5);

        expect($input->value())->toBe('abcde')
            ->and(test()->loop->posted)->toBe([]);
    });

    it('takes borderless, read-only and a maximum length', function (): void {
        $input = screen()->textInput('t');

        $input->setBorderless()->setReadOnly()->setMaxLength(10);
        expect($input->isBorderless())->toBeTrue()
            ->and($input->isReadOnly())->toBeTrue()
            ->and($input->maxLength())->toBe(10)
            ->and(told($input))->toBe([['applyBorderless', [true]], ['applyReadOnly', [true]], ['applyMaxLength', [10]]])
            ->and(fn () => $input->setMaxLength(0))->toThrow(ToolkitException::class, '1 or more');
    });
});

describe('in a view', function (): void {
    it('sets only what changed, and puts the defaults back when a setting goes', function (): void {
        $view = scripted(fn (ScriptedView $v) => Column::make()->with(
            ($v->state['styled'] ?? false)
                ? Button::make('Go')->style(ButtonStyle::LINK)->cursor(Cursor::TEXT)->tooltip('tip')->margin('4 8')->border(1)
                : Button::make('Go'),
        ));
        $button = at('column1.button1');
        expect($button->hooks())->toBe(['bootstrapNative']);

        $view->state['styled'] = true;
        rerender();
        $button->calls = [];
        rerender();
        expect($button->calls)->toBe([]);

        $view->state['styled'] = false;
        rerender();
        expect(at('column1.button1'))->toBe($button)
            ->and($button->style())->toBe(ButtonStyle::DEFAULT)
            ->and($button->cursor())->toBeNull()
            ->and($button->tooltip())->toBeNull()
            ->and($button->margin())->toEqual(Insets::none())
            ->and($button->borderWidth())->toBe(0);
    });

    it('builds a text input again when it turns into a search field', function (): void {
        $view = scripted(fn (ScriptedView $v) => Column::make()->with(TextInput::make(search: $v->state['search'] ?? false)));
        $input = at('column1.textInput1');

        $view->state['search'] = true;
        rerender();

        expect($input->isRemoved())->toBeTrue()
            ->and(at('column1')->children()[0]->isSearch())->toBeTrue();
    });

    it('lays a margin set on a component over its root', function (): void {
        template('chip', "@template\n<Row><Label>chip</Label></Row>\n@endtemplate");

        sharp('<Column><Chip margin="6 0" /></Column>');

        expect(at('column1.chip1')->margin())->toEqual(new Insets(6, 0, 6, 0));
    });
});

describe('in Sharp', function (): void {
    it('reads every new attribute', function (): void {
        sharp(<<<'S'
            <Row padding="40 16 8">
                <Column padding="0 0 0 12" margin="12 0" cursor="pointer" tooltip="Hello" border="1 #33669980">
                    <TextInput placeholder="Search" search borderless readOnly maxLength="20" />
                    <Button style="link" symbol="star" icon="/tmp/a.png" role="cancel" color="#ff0000" textAlign="left" font="13 semibold">Discover</Button>
                    <Link url="https://example.com" color="#00ff00">Docs</Link>
                </Column>
            </Row>
            S);

        $row = at('row1');
        $column = at('row1.column1');
        $input = at('row1.column1.textInput1');
        $button = at('row1.column1.button2');
        $link = at('row1.column1.link3');

        expect($row->padding())->toEqual(new Insets(40, 16, 8, 16))
            ->and($column->padding())->toEqual(new Insets(0, 0, 0, 12))
            ->and($column->margin())->toEqual(new Insets(12, 0, 12, 0))
            ->and($column->cursor())->toBe(Cursor::POINTER)
            ->and($column->tooltip())->toBe('Hello')
            ->and([$column->borderWidth(), $column->borderColor()])->toEqual([1, Color::hex('#33669980')])
            ->and($input->isSearch())->toBeTrue()
            ->and($input->isBorderless())->toBeTrue()
            ->and($input->isReadOnly())->toBeTrue()
            ->and($input->maxLength())->toBe(20)
            ->and($button->style())->toBe(ButtonStyle::LINK)
            ->and($button->symbol())->toBe('star')
            ->and($button->icon())->toBe('/tmp/a.png')
            ->and($button->role())->toBe(ButtonRole::CANCEL)
            ->and($button->cursor())->toBeNull()
            ->and(toldWith($button, 'applyCursor'))->toContain([Cursor::POINTER])
            ->and(toldWith($button, 'applyTextColor'))->toEqual([[Color::hex('#ff0000')]])
            ->and(toldWith($link, 'applyTextColor'))->toEqual([[Color::hex('#00ff00')]]);
    });

    it('takes padding as an int and as PHP', function (): void {
        sharp('<Column padding="16"><Row :padding="[1, 2, 3, 4]" /></Column>');

        expect(at('column1')->padding())->toEqual(Insets::of(16))
            ->and(at('column1.row1')->padding())->toEqual(new Insets(1, 2, 3, 4));
    });

    it('says what is wrong at compile time', function (string $markup, string $says): void {
        expect(fn () => sharp($markup))->toThrow(SharpCompileError::class, $says);
    })->with([
        'five sides' => ['<Column padding="1 2 3 4 5" />', 'one to four sizes'],
        'a unit' => ['<Column margin="12px" />', 'one to four sizes'],
        'a cursor' => ['<Column cursor="hand" />', "'hand' is none of"],
        'a style' => ['<Column><Button style="ghost">x</Button></Column>', "'ghost' is none of"],
    ]);
});
