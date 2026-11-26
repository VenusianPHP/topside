<?php

declare(strict_types=1);

use Surface\Toolkits\Presentation;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\ViewException;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\Styling\TextRun;
use Surface\Contracts\Toolkits\UI\IconPosition;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Element;
use Surface\Toolkits\Views\Sharp\SharpCompileError;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

/*
| What a long read needs: a paragraph whose words differ in look, a column that stops
| growing, an icon after a button's label, a view that knows its window, and a view that
| shows itself again when work it started outside a handler comes back.
*/
beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});
afterEach(fn () => dropSharpApp());

/** The arguments of each time $hook was told to $primitive, oldest first. */
function storyToldWith(object $primitive, string $hook): array
{
    return array_values(array_map(static fn (array $call): array => $call[1], array_filter($primitive->calls, static fn (array $call): bool => $call[0] === $hook)));
}

/** A card inside a page, whose text is filled in later. */
class StoryCard extends Component
{
    public string $title = 'loading';

    public function render(): Element
    {
        return Label::make($this->title);
    }
}

describe('runs in a label', function (): void {
    it('sets the runs and their text, and tells the toolkit the runs', function (): void {
        $label = screen()->label('lead', '');
        $runs = [new TextRun('Today: ', bold: true), new TextRun('rain', color: Color::hex('#888888'))];
        $label->setRuns($runs);

        expect($label->text())->toBe('Today: rain')
            ->and($label->runs())->toBe($runs)
            ->and(storyToldWith($label, 'applyRuns'))->toBe([[$runs]]);
    });

    it('is one plain run again after setText()', function (): void {
        $label = screen()->label('lead', '');
        $label->setRuns([new TextRun('a', bold: true)])->setText('b');

        expect($label->runs())->toEqual([new TextRun('b')]);
    });

    it('refuses what is not a run, and once removed', function (): void {
        $label = screen()->label('lead', '');

        expect(fn () => $label->setRuns(['text']))->toThrow(ToolkitException::class, "runs are TextRuns, got string");

        $label->remove();

        expect(fn () => $label->setRuns([]))->toThrow(ToolkitException::class);
    });

    it('reads <b>, <i> and <span color> inside a label, nested and with {{ }}', function (): void {
        sharp(<<<'S'
            <Label wrap>
                <b>{{ $date }}: {{ $title }}.</b>
                It is <span color="#ff0000">red <i>and</i> hot</span>.
            </Label>
            S, <<<'S'
            public string $date = 'Nov 22';
            public string $title = 'Jupiter';
            S);

        expect(at('label1')->text())->toBe('Nov 22: Jupiter. It is red and hot.')
            ->and(at('label1')->runs())->toEqual([
                new TextRun('Nov 22: Jupiter.', bold: true),
                new TextRun(' It is '),
                new TextRun('red ', color: Color::hex('#ff0000')),
                new TextRun('and', italic: true, color: Color::hex('#ff0000')),
                new TextRun(' hot', color: Color::hex('#ff0000')),
                new TextRun('.'),
            ]);
    });

    it('changes the runs in place when the state does', function (): void {
        sharp('<Label><b>{{ $title }}</b> body</Label>', 'public string $title = "One";');
        $label = at('label1');

        $page = Presentation::driver()->view()->component;
        $page->title = 'Two';
        $page->refresh();

        expect(at('label1'))->toBe($label)
            ->and($label->runs())->toEqual([new TextRun('Two', bold: true), new TextRun(' body')]);
    });

    it('goes back to plain text when a label stops having runs', function (): void {
        $page = scripted(fn () => Label::make('plain')->runs([new TextRun('plain', bold: true)]));
        ScriptedView::$describe = fn () => Label::make('plain');
        $page->refresh();

        expect(at('label1')->runs())->toEqual([new TextRun('plain')]);
    });

    it('says what is wrong at compile time', function (string $markup, string $says): void {
        expect(fn () => sharp($markup))->toThrow(SharpCompileError::class, $says);
    })->with([
        'another tag' => ['<Label>a <Button>b</Button></Label>', 'holds text, {{ }}, <b>, <i> and <span'],
        'a span with no colour' => ['<Label>a <span>b</span></Label>', '<span> in a label needs a color'],
        'an attribute on <b>' => ['<Label>a <b x="1">b</b></Label>', '<b> in a label takes no attributes'],
        'both text and runs' => ['<Label text="x">a <b>b</b></Label>', 'both a text attribute and text inside'],
    ]);
});

describe('a maximum size', function (): void {
    it('tells the toolkit, the container first, and refuses a negative', function (): void {
        $column = screen()->column('page')->maxSize(768, 0);

        expect(storyToldWith($column, 'applyMaxSize'))->toBe([[768, 0]])
            ->and(fn () => $column->maxSize(-1, 0))->toThrow(ToolkitException::class, 'A maximum size is 0 (none) or more');
    });

    it('is an attribute', function (): void {
        sharp('<Column maxSize="768 0" fill="horizontal" />');

        expect(storyToldWith(at('column1'), 'applyMaxSize'))->toBe([[768, 0]]);
    });
});

describe('an icon after the label', function (): void {
    it('tells the toolkit which side', function (): void {
        sharp('<Button symbol="arrow.right" iconPosition="trailing">More</Button>');

        expect(at('button1')->iconPosition())->toBe(IconPosition::TRAILING)
            ->and(storyToldWith(at('button1'), 'applyIconPosition'))->toBe([[IconPosition::TRAILING]]);
    });

    it('is leading until set', function (): void {
        expect(screen()->button('b', 'x')->iconPosition())->toBe(IconPosition::LEADING);
    });
});

describe('a view\'s window and refresh()', function (): void {
    it('gives the page and every component inside it the window it is in', function (): void {
        scripted(fn () => Column::make()->with(StoryCard::make()));
        $driver = Presentation::driver();

        expect($driver->view()->component->window())->toBe($driver->window())
            ->and(nestedCard()->window())->toBe($driver->window());
    });

    it('shows a nested component again when its state changes outside a handler', function (): void {
        scripted(fn () => Column::make()->with(StoryCard::make()));
        $card = nestedCard();

        $card->title = 'Swirls on Jupiter';
        $card->refresh();

        expect(at('column1.storyCard1')->text())->toBe('Swirls on Jupiter');
    });

    it('does nothing once the view is off screen', function (): void {
        scripted(fn () => Column::make()->with(StoryCard::make()));
        $card = nestedCard();
        scripted(fn () => Label::make('elsewhere'));

        $card->title = 'late';
        $card->refresh();

        expect(at('label1')->text())->toBe('elsewhere');
    });

    it('does nothing for a component that is not mounted, and has no window', function (): void {
        $loose = new StoryCard();
        $loose->refresh();

        expect(fn () => $loose->window())->toThrow(ViewException::class, 'StoryCard is not mounted in a window.');
    });
});

/** The StoryCard mounted inside the scripted page. */
function nestedCard(): StoryCard
{
    $found = null;
    $walk = function ($node) use (&$walk, &$found): void {
        if (($node->component ?? null) instanceof StoryCard) {
            $found = $node->component;
        }
        foreach ($node->children ?? [] as $child) {
            $walk($child);
        }
    };
    $host = (new ReflectionProperty(Presentation::driver()->view(), 'host'))->getValue(Presentation::driver()->view());
    $walk($host);

    return $found ?? throw new RuntimeException('No StoryCard is mounted.');
}

describe('a homogeneous row or column', function (): void {
    it('tells the toolkit, and is off until set', function (): void {
        $row = screen()->row('cards');
        $column = screen()->column('list');

        expect($row->isHomogeneous())->toBeFalse();

        $row->setHomogeneous();
        $column->setHomogeneous()->setHomogeneous(false);

        expect($row->isHomogeneous())->toBeTrue()
            ->and(storyToldWith($row, 'applyHomogeneous'))->toBe([[true]])
            ->and(storyToldWith($column, 'applyHomogeneous'))->toBe([[true], [false]]);
    });

    it('is an attribute, changed in place', function (): void {
        sharp('<Row :homogeneous="$even"><Label>a</Label></Row>', 'public bool $even = true;');
        $row = at('row1');
        $page = Presentation::driver()->view()->component;
        $page->even = false;
        $page->refresh();

        expect(at('row1'))->toBe($row)
            ->and(storyToldWith($row, 'applyHomogeneous'))->toBe([[true], [false]]);
    });
});
