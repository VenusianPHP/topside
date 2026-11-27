<?php

declare(strict_types=1);

use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Components\LazyColumn;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;

/*
| A LazyColumn builds only the rows the page shows, first to last, between a spacer above and
| a spacer below sized as the rows they stand for. It works out from its own scrolling which
| rows are in view (overscan beyond each edge) and asks the page for them; near the end it
| asks for more.
*/
beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});
afterEach(fn () => dropSharpApp());

/** A page of $rows rows, 50 high with 10 between, showing first to last as the list asks. */
function lazyPage(int $rows = 100): object
{
    return sharp(<<<'SHARP'
        <LazyColumn :count="$rows" rowHeight="50" spacing="10" :first="$first" :last="$last" @window="shown" @nearEnd="more">
            @foreach (range($first, $last) as $r)
                <Label :key="'row-'.$r">{{ 'Row '.$r }}</Label>
            @endforeach
        </LazyColumn>
        SHARP, <<<'PHP'
        public int $rows = 100;
        public int $first = 0;
        public int $last = 7;
        public array $asked = [];
        public int $near = 0;
        public function shown(int $first, int $last): void
        {
            $this->asked[] = [$first, $last];
            [$this->first, $this->last] = [$first, $last];
        }
        public function more(): void
        {
            $this->near++;
        }
        PHP, ['rows' => $rows]);
}

/** What the list's scroll view holds: each child's name, a label's text, a spacer's height. */
function lazyShown(): array
{
    $content = at('lazyColumn1')->children()[0] ?? null;

    return array_map(static fn ($child): string => method_exists($child, 'text')
        ? $child->text()
        : 'spacer '.(fn () => $this->minimumSize())->call($child)[1], $content?->children() ?? []);
}

it('builds the rows the page shows and a spacer for the rows after them', function (): void {
    lazyPage();

    expect(lazyShown())->toBe([
        'Row 0', 'Row 1', 'Row 2', 'Row 3', 'Row 4', 'Row 5', 'Row 6', 'Row 7',
        'spacer '.((100 - 8) * 60 - 10),
    ]);
});

it('asks for the rows in view, overscan beyond each edge, and builds them between two spacers', function (): void {
    $page = lazyPage();

    // Half way down a quarter shows: rows 49 to 74 of 5990 points, two more each way.
    at('lazyColumn1')->nativeScrolledTo(0.5);
    deliverPosted();

    $shown = lazyShown();
    expect($page->asked)->toBe([[47, 76]])
        ->and($shown[0])->toBe('spacer '.(47 * 60 - 10))
        ->and($shown[1])->toBe('Row 47')
        ->and($shown[30])->toBe('Row 76')
        ->and($shown[31])->toBe('spacer '.((99 - 76) * 60 - 10))
        ->and($shown)->toHaveCount(32);
});

it('asks nothing while the rows in view stay the same', function (): void {
    $page = lazyPage();
    at('lazyColumn1')->nativeScrolledTo(0.5);
    deliverPosted();

    at('lazyColumn1')->nativeScrolledTo(0.5001);
    deliverPosted();

    expect($page->asked)->toBe([[47, 76]]);
});

it('asks for more past nearEnd, and keeps the last row the last there is', function (): void {
    $page = lazyPage();

    at('lazyColumn1')->nativeScrolledTo(0.5);
    deliverPosted();
    expect($page->near)->toBe(0);

    at('lazyColumn1')->nativeScrolledTo(0.75);
    deliverPosted();
    expect($page->near)->toBe(1)
        ->and(end($page->asked)[1])->toBe(99)
        ->and(array_slice(lazyShown(), -1))->toBe(['Row 99']);
});

it('takes the padding into the rows\' places', function (): void {
    $list = new LazyColumn();
    [$list->count, $list->rowHeight, $list->spacing, $list->padding, $list->overscan] = [10, 100, 0, '500 0', 0];

    // 2000 points: 500 above, 1000 of rows, 500 below. The top half shows the padding and rows 0 to 4.
    expect($list->inView(new ScrollPosition(0.0, 0.0, 1.0, 0.5)))->toBe([0, 5])
        ->and($list->inView(new ScrollPosition(0.0, 0.5, 1.0, 0.25)))->toBe([5, 9]);
});

it('refuses a row height that is not positive', function (): void {
    expect(fn () => sharp('<LazyColumn :count="3" rowHeight="0" />'))
        ->toThrow(ViewException::class, "A LazyColumn's rowHeight is the height of every row, more than 0; got 0.");
});
