<?php

declare(strict_types=1);

use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Elements\Tab;
use Surface\Toolkits\Views\Elements\Grid;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\TabView;
use Surface\Toolkits\Views\Elements\SplitView;
use Surface\Toolkits\Views\Elements\ScrollView;
use Surface\Toolkits\Views\Sharp\SharpCompileError;
use Surface\Contracts\Toolkits\UI\Placement;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

/*
| Scroll view, tab view, split view and grid: their rules, and how they are written. The
| fakes select and move on their own when told something, as the toolkits do.
*/
beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});

afterEach(fn () => dropSharpApp());

function root(): mixed
{
    return screen()->children()[0];
}

/** Each child's text and where it is: "row,column,columns". */
function cells(): array
{
    $cells = [];
    foreach (root()->children() as $child) {
        $cell = root()->cellOf($child);
        $cells[$child->text()] = "{$cell->row},{$cell->column},{$cell->columnSpan}";
    }

    return $cells;
}

function tabTitles(): array
{
    return array_map(fn (string $page): string => root()->title($page), root()->pages());
}

function selectedTitle(): ?string
{
    return is_null(root()->selected()) ? null : root()->title(root()->selected());
}

describe('the primitives', function (): void {
    it('refuses what a scroll view, tab view or split cannot hold', function (): void {
        $scroll = screen()->scrollView('scroll');
        expect(fn () => $scroll->label('text', 'x'))->toThrow(ToolkitException::class, 'scrolls one container');
        $scroll->column('content');
        expect(fn () => $scroll->column('other'))->toThrow(ToolkitException::class)
            ->and(fn () => $scroll->setScrollbars(false, false))->toThrow(ToolkitException::class);

        $tabs = screen()->tabs('tabs');
        expect(fn () => $tabs->column('page'))->toThrow(ToolkitException::class, 'addPage');

        $split = screen()->split('split');
        $split->column('a');
        $split->column('b');
        expect(fn () => $split->column('c'))->toThrow(ToolkitException::class)
            ->and(fn () => $split->setPositions([0.2, 0.6]))->toThrow(ToolkitException::class)
            ->and(fn () => $split->setPositions([1.2]))->toThrow(ToolkitException::class);
    });

    it('keeps the tab selected through inserts and moves, and picks one when it goes', function (): void {
        $tabs = screen()->tabs('tabs');
        $a = $tabs->addPage('a', 'A');
        $c = $tabs->addPage('c', 'C');
        $tabs->select('c');

        $tabs->addPage('b', 'B', 1);
        $a->moveTo(2);
        expect($tabs->pages())->toBe(['b', 'c', 'a'])->and($tabs->selected())->toBe('c');

        $c->remove();
        expect($tabs->selected())->toBe('a')->and(test()->loop->posted)->toBe([]);

        $tabs->remove();
        expect($tabs->selected())->toBeNull();
    });

    it('lets children trade cells, and refuses two in one', function (): void {
        $grid = screen()->grid('grid');
        $a = $grid->at(0, 0)->label('a', 'A');
        $b = $grid->at(0, 1)->label('b', 'B');

        $grid->arrangeCells([[$a, Placement::cell(0, 1)], [$b, Placement::cell(0, 0)]]);
        expect($grid->cellOf($a)->column)->toBe(1)
            ->and(fn () => $grid->arrangeCells([[$a, Placement::cell(0, 0)]]))->toThrow(ToolkitException::class)
            ->and($grid->cellOf($a)->column)->toBe(1)
            ->and(fn () => $grid->at(0, 0))->toThrow(ToolkitException::class);
    });

    it('posts a drag, and none of its own writes', function (): void {
        $split = screen()->split('split');
        $split->column('a');
        $split->column('b');
        $split->setPositions([0.3]);
        flushDrags();
        expect(test()->loop->posted)->toBe([])->and($split->positions())->toBe([0.3]);

        $split->nativeDragged(0.6);
        flushDrags();
        expect(test()->loop->posted)->toHaveCount(1)->and($split->positions())->toBe([0.6]);
    });
});

describe('in a view', function (): void {
    it('is the same from Sharp as from composition', function (): void {
        scripted(fn () => SplitView::make(0.3)->with(
            ScrollView::make(spacing: 4)->with(Label::make('one'), Label::make('two')),
            TabView::make(1)->with(
                Tab::make('A', padding: 8)->with(Label::make('a')),
                Tab::make('B')->with(Grid::make(3, spacing: 2)->with(Label::make('x')->span(2), Label::make('y'))),
            ),
        ));
        $composed = screenShape();

        sharp(<<<'SHARP'
            <SplitView position="0.3">
                <ScrollView spacing="4">
                    <Label>one</Label>
                    <Label>two</Label>
                </ScrollView>
                <TabView selected="1">
                    <Tab title="A" padding="8"><Label>a</Label></Tab>
                    <Tab title="B">
                        <Grid columns="3" spacing="2">
                            <Label span="2">x</Label>
                            <Label>y</Label>
                        </Grid>
                    </Tab>
                </TabView>
            </SplitView>
            SHARP);

        expect(screenShape())->toBe($composed);
    });

    it('tells a scroll handler where the view is, and watches only while there is one', function (): void {
        $seen = new ArrayObject();
        $view = scripted(fn (Venusian\Topside\Tests\Fakes\Views\ScriptedView $v) => ($v->state['watch'] ?? true)
            ? ScrollView::make()->with(Label::make('one'))->onScroll(fn ($at, $mail) => $seen->append([$at->y, $at->height, $mail::class]))
            : ScrollView::make()->with(Label::make('one')));
        $scroll = at('scrollView1');

        expect($scroll->isWatchingScroll())->toBeTrue();
        $scroll->nativeScrolledTo(0.25);
        $scroll->nativeScrolledTo(0.75);
        deliverPosted();
        expect($seen->getArrayCopy())->toBe([[0.75, 0.25, Surface\Contracts\Toolkits\Mail\Views\ViewScrolled::class]])
            ->and($scroll->scrollPosition()->y)->toBe(0.75);

        $view->state['watch'] = false;
        rerender();
        expect($scroll->isWatchingScroll())->toBeFalse();
        $scroll->nativeScrolledTo(0.5);
        deliverPosted();
        expect($seen)->toHaveCount(1);
    });

    it('takes @scroll in Sharp', function (): void {
        $page = sharp('<ScrollView @scroll="fn ($at) => $this->at = $at->y"><Label>a</Label></ScrollView>', 'public float $at = 0.0;');

        at('scrollView1')->nativeScrolledTo(0.5);
        deliverPosted();

        expect($page->at)->toBe(0.5);
    });

    it('scrolls any number of children, and fills unless told not to', function (): void {
        sharp('<Column><ScrollView><Label>a</Label><Label>b</Label></ScrollView><ScrollView fill="false" /></Column>');
        [$first, $second] = root()->children();

        expect($first->content()->children())->toHaveCount(2)
            ->and($first->hooks())->toContain('applyFill')
            ->and($second->hooks())->not->toContain('applyFill');
    });

    it('flows a grid, and flows it again around what comes and goes', function (): void {
        $view = sharp(<<<'SHARP'
            <Grid columns="3">
                <Label>a</Label>
                @if ($extra)
                    <Label>extra</Label>
                @endif
                <Label span="2">b</Label>
                <Label :hidden="$hide">c</Label>
                <Label>d</Label>
            </Grid>
            SHARP, 'public bool $extra = false; public bool $hide = false;');

        expect(cells())->toBe(['a' => '0,0,1', 'b' => '0,1,2', 'c' => '1,0,1', 'd' => '1,1,1']);

        $view->extra = true;
        $view->hide = true;
        rerender();

        expect(cells())->toBe(['a' => '0,0,1', 'extra' => '0,1,1', 'b' => '1,0,2', 'c' => '2,0,1', 'd' => '1,2,1']);
    });

    it('trades the cells of a reordered loop without building', function (): void {
        $view = sharp(<<<'SHARP'
            <Grid columns="2">
                @foreach ($words as $word)
                    <Label :key="$word">{{ $word }}</Label>
                @endforeach
            </Grid>
            SHARP, "public array \$words = ['a', 'b', 'c'];");
        $before = built();

        $view->words = ['c', 'b', 'a'];
        rerender();

        expect(cells())->toBe(['c' => '0,0,1', 'b' => '0,1,1', 'a' => '1,0,1'])->and(built())->toBe($before);
    });

    it('needs no state for tabs: the user\'s choice outlasts a render', function (): void {
        $view = sharp('<TabView><Tab title="A" /><Tab :title="$second" /></TabView>', "public string \$second = 'B';");

        root()->nativeChosen(1);
        deliverPosted();
        $view->second = 'Bee';
        rerender();

        expect(selectedTitle())->toBe('Bee')->and(tabTitles())->toBe(['A', 'Bee']);
    });

    it('runs @change with a tab\'s place and title, and follows selected', function (): void {
        $view = sharp('<TabView :selected="$tab" @change="chose"><Tab title="A" /><Tab title="B" /><Tab title="C" /></TabView>', <<<'PHP'
            public int $tab = 2;
            public string $last = '';

            public function chose(int $index, string $title): void
            {
                $this->tab = $index;
                $this->last = $title;
            }
            PHP);
        expect(selectedTitle())->toBe('C');

        root()->nativeChosen(1);
        deliverPosted();
        expect([$view->tab, $view->last, selectedTitle()])->toBe([1, 'B', 'B']);

        $view->tab = 0;
        rerender();
        expect(selectedTitle())->toBe('A');
    });

    it('puts a tab in the middle without building the others', function (): void {
        $view = sharp(<<<'SHARP'
            <TabView :selected="$tab">
                <Tab title="A" />
                @if ($more)
                    <Tab title="B" />
                @endif
                <Tab title="C" />
                <Tab title="D" :hidden="!$admin" />
            </TabView>
            SHARP, 'public int $tab = 1; public bool $more = false; public bool $admin = false;');
        expect(tabTitles())->toBe(['A', 'C'])->and(selectedTitle())->toBe('C');
        $c = root()->children()[1];

        $view->more = true;
        $view->admin = true;
        rerender();

        expect(tabTitles())->toBe(['A', 'B', 'C', 'D'])
            ->and(root()->children()[2])->toBe($c)
            ->and(selectedTitle())->toBe('B')
            ->and(test()->loop->posted)->toBe([]);
    });

    it('takes a template whose root is a Tab', function (): void {
        template('parts.about-tab', "@template\n<Tab title=\"About\"><Label>hello</Label></Tab>\n@endtemplate\n");

        sharp('<TabView><Tab title="Home" /><AboutTab /></TabView>');

        expect(tabTitles())->toBe(['Home', 'About'])->and(root()->children()[1]->children()[0]->text())->toBe('hello');
    });

    it('keeps a dragged divider, and reports it', function (): void {
        $view = sharp(
            '<SplitView :position="$at" @change="fn ($p) => $this->at = $p"><Column /><Column /></SplitView>',
            'public float $at = 0.25;',
        );
        expect(root()->positions())->toBe([0.25]);

        root()->nativeDragged(0.7);
        deliverPosted();

        expect($view->at)->toBe(0.7)->and(root()->positions())->toBe([0.7]);
    });

    it('swaps keyed panes without building', function (): void {
        $view = scripted(fn (ScriptedView $v) => SplitView::make()->with(
            ...array_map(fn (string $key) => Column::make()->key($key), $v->state['order']),
        ), ['state' => ['order' => ['a', 'b']]]);
        [$a, $b] = root()->children();
        $before = built();

        $view->state = ['order' => ['b', 'a']];
        rerender();

        expect(root()->children())->toBe([$b, $a])->and(built())->toBe($before);
    });

    it('says where a Tab and a TabView\'s children go', function (): void {
        expect(fn () => sharp("<TabView>\n<Label>x</Label>\n</TabView>"))
            ->toThrow(SharpCompileError::class, 'put <Label> inside a <Tab')
            ->and(fn () => sharp('<Column><Tab title="lost" /></Column>'))
            ->toThrow(ViewException::class, 'goes straight inside one');
    });

    it('says what is wrong with a split or a grid', function (): void {
        expect(fn () => sharp('<SplitView><Column /><Column /><Column /></SplitView>'))
            ->toThrow(ViewException::class, 'two panes')
            ->and(fn () => sharp('<Grid columns="0" />'))
            ->toThrow(ViewException::class, '1 column or more');
    });
});
