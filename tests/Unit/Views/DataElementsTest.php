<?php

declare(strict_types=1);

use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Elements\Tree;
use Surface\Toolkits\Views\Elements\Table;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\TextArea;
use Surface\Toolkits\Views\Elements\ListView;
use Surface\Toolkits\Views\Elements\CollectionView;

/*
| Text area, list, collection, table and tree, written with plain arrays. The fakes signal
| what code writes to them, as the toolkits do.
*/
beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});

afterEach(fn () => dropSharpApp());

function data(int $at = 0): mixed
{
    return screen()->children()[0]->children()[$at];
}

it('is the same from Sharp as from composition', function (): void {
    scripted(fn () => Column::make()->with(
        TextArea::make('notes')->readOnly(),
        ListView::make(['One', ['title' => 'Two', 'detail' => 'second']])->rowHeight(40),
        CollectionView::make(['A'])->cellSize(90, 80),
        Table::make([['name' => 'Ada', 'age' => 36]]),
        Tree::make(['src' => ['a.php']]),
    ));
    $composed = screenShape();

    sharp(<<<'SHARP'
        <Column>
            <TextArea value="notes" readOnly />
            <ListView :items="['One', ['title' => 'Two', 'detail' => 'second']]" rowHeight="40" />
            <CollectionView :items="['A']" cellSize="90 80" />
            <Table :rows="[['name' => 'Ada', 'age' => 36]]" />
            <Tree :nodes="['src' => ['a.php']]" />
        </Column>
        SHARP);

    expect(screenShape())->toBe($composed);
});

it('keeps what is typed, and posts nothing code writes', function (): void {
    $view = sharp(
        '<Column><TextArea :value="$notes" @change="fn ($t) => $this->notes = $t" /></Column>',
        "public string \$notes = '';",
    );

    data()->nativeTyped("one\r\ntwo");
    deliverPosted();
    expect($view->notes)->toBe("one\ntwo")->and(data()->lineCount())->toBe(2);

    $view->notes = 'from code';
    rerender();
    expect(data()->value())->toBe('from code')->and(test()->loop->posted)->toBe([]);
});

it('changes only the items that differ', function (): void {
    $view = sharp('<Column><ListView :items="$songs" /></Column>', "public array \$songs = ['a', 'b', 'c'];");
    data()->calls = [];

    $view->songs = ['a', 'x', 'b', 'c'];
    rerender();

    expect(data()->hooks())->toBe(['applyInserted'])
        ->and(array_map(fn ($row) => $row->title, data()->items()))->toBe(['a', 'x', 'b', 'c']);
});

it('gives @activate and @action the item as it was written', function (): void {
    $view = sharp(<<<'SHARP'
        <Column>
            <ListView :items="$songs" @activate="fn ($song) => $this->heard[] = $song['id']" @action="fn ($song, $at) => $this->heard[] = $at" />
        </Column>
        SHARP, <<<'PHP'
        public array $songs = [['id' => 7, 'title' => 'Seven'], ['id' => 9, 'title' => 'Nine', 'enabled' => false]];
        public array $heard = [];
        PHP);

    data()->nativeActivated(0);
    data()->nativeAction(0);
    data()->nativeActivated(1);
    deliverPosted();

    expect($view->heard)->toBe([7, 0]);
});

it('takes a table\'s columns from the rows, or from what is said', function (): void {
    sharp(<<<'SHARP'
        <Column>
            <Table :rows="[['first_name' => 'Ada', 'age' => 36]]" />
            <Table :columns="['name' => 'Who']" :rows="[['Grace']]" />
        </Column>
        SHARP);

    expect(array_map(fn ($c) => $c->label, data(0)->columns()))->toBe(['First name', 'Age'])
        ->and(data(0)->rows())->toBe([['first_name' => 'Ada', 'age' => '36']])
        ->and(data(1)->rows())->toBe([['name' => 'Grace']]);
});

it('runs @select with the row, and keeps it while the row is the same', function (): void {
    $view = sharp(
        '<Column><Table :rows="$rows" @select="fn ($row) => $this->who = $row[\'name\'] ?? null" /></Column>',
        "public array \$rows = [['name' => 'Ada'], ['name' => 'Grace']]; public ?string \$who = null;",
    );

    data()->nativeChose(1);
    deliverPosted();
    expect($view->who)->toBe('Grace');

    $view->rows[] = ['name' => 'Alan'];
    rerender();
    expect(data()->selectedRow())->toBe(1)->and(test()->loop->posted)->toBe([]);
});

it('reads a tree from nested arrays, and keeps what the user opened', function (): void {
    $view = sharp(<<<'SHARP'
        <Column><Tree :nodes="$files" @select="fn ($id) => $this->picked = $id" /></Column>
        SHARP, "public array \$files = ['src' => ['Http' => ['Kernel.php']], 'README.md']; public ?string \$picked = null;");

    data()->nativeOpened('src', true);
    data()->nativeChose('src/Http');
    deliverPosted();
    expect($view->picked)->toBe('src/Http');

    $view->files[] = 'LICENSE';
    rerender();

    expect(data()->isExpanded('src'))->toBeTrue()
        ->and(data()->selectedNode())->toBe('src/Http')
        ->and(test()->loop->posted)->toBe([]);
});

it('opens and selects what code says', function (): void {
    $view = sharp(
        '<Column><Tree :nodes="$nodes" :selected="$at" :expanded="$open" /></Column>',
        "public array \$nodes = [['id' => '1', 'label' => 'One', 'children' => [['id' => '2', 'label' => 'Two']]]]; public string \$at = ''; public array \$open = [];",
    );
    expect(data()->isExpanded('1'))->toBeFalse();

    $view->open = ['1'];
    $view->at = '2';
    rerender();

    expect(data()->isExpanded('1'))->toBeTrue()->and(data()->selectedNode())->toBe('2');
});

it('says what an item, a row and a node should be', function (string $markup, string $says): void {
    expect(fn () => sharp("<Column>{$markup}</Column>"))->toThrow(ViewException::class, $says);
})->with([
    'list item' => ['<ListView :items="[[\'name\' => \'x\']]" />', "an array with 'title'"],
    'table row' => ['<Table :rows="[\'x\']" />', 'is an array of its cells'],
    'tree node' => ['<Tree :nodes="[[\'name\' => \'x\']]" />', 'an id or a label'],
]);

it('selects nothing once a node is closed over the selection', function (): void {
    $view = sharp(
        '<Column><Tree :nodes="$files" @select="fn ($id) => $this->picked = $id" /></Column>',
        "public array \$files = ['src' => ['a.php']]; public ?string \$picked = null;",
    );

    data()->nativeOpened('src', true);
    data()->nativeChose('src/a.php');
    deliverPosted();
    expect($view->picked)->toBe('src/a.php');

    data()->nativeOpened('src', false);
    deliverPosted();
    expect($view->picked)->toBeNull()->and(data()->selectedNode())->toBeNull();

    data()->selectNode('src/a.php');
    data()->expand('src', false);
    expect(data()->selectedNode())->toBeNull()->and(test()->loop->posted)->toBe([]);
});
