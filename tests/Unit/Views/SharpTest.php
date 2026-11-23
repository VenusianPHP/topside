<?php

declare(strict_types=1);

use Surface\Toolkits\View;
use Surface\Toolkits\Presentation;
use Surface\Toolkits\Views\Justify;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Elements\Row;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Toggle;
use Surface\Toolkits\Views\Elements\Checkbox;
use Surface\Toolkits\Views\Elements\Dropdown;
use Surface\Toolkits\Views\Sharp\TagResolver;
use Surface\Toolkits\Views\Sharp\SharpCompileError;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\FontWeight;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});
afterEach(fn () => dropSharpApp());

describe('the same view as composition', function (): void {
    it('builds exactly what the hand-written equivalent builds', function (): void {
        scripted(fn () => Column::make(spacing: 12, padding: 4)->justify(Justify::CENTER)->with(
            Label::make('Hello & welcome')->align(Align::CENTER)->font(new FontSpec(15.0, FontWeight::BOLD, italic: true))->textAlign(TextAlignment::RIGHT)->wrap(),
            Row::make(spacing: 8)->fill(true, false)->with(
                Checkbox::make('Yes', checked: true)->minSize(340, 0),
                Toggle::make(on: false)->disabled(),
                Dropdown::make(['A', 'B'], selected: 1)->opacity(0.5),
            ),
            Button::make('Go')->align(Align::START, Align::END)->background(Color::hex('#336699'))->cornerRadius(6.0)->hidden(),
        ));
        $composed = screenShape();

        sharp(<<<'S'
            <Column spacing="12" padding="4" justify="center">
                <Label align="center" font="15 bold italic" text-align="right" wrap>Hello &amp; welcome</Label>
                <Row spacing="8" fill="horizontal">
                    <Checkbox checked minSize="340 0">Yes</Checkbox>
                    <Toggle on="false" disabled />
                    <Dropdown :options="['A', 'B']" selected="1" opacity="0.5" />
                </Row>
                <Button align="start end" background="#336699" cornerRadius="6" hidden>Go</Button>
            </Column>
            S);

        expect(screenShape())->toBe($composed);
    });

    it('changes only what a click changed', function (): void {
        $view = sharp(<<<'S'
            <Column>
                <Label>Clicked {{ $count }}
                    times</Label>
                <Button @click="add">Add</Button>
            </Column>
            S, 'public int $count = 0; public function add(): void { $this->count++; }');
        $label = at('column1.label1');
        $label->calls = [];
        $built = built();

        click(at('column1.button2'));

        expect($view->count)->toBe(1)
            ->and($label->text())->toBe('Clicked 1 times')
            ->and($label->hooks())->toBe(['applyText'])
            ->and(built())->toBe($built);
    });

    it('takes props, and refuses one it does not have', function (): void {
        sharp('<Label>{{ $name }}</Label>', "public string \$name = '';", ['name' => 'Venus']);
        expect(at('label1')->text())->toBe('Venus');

        expect(fn () => View::render('page', ['nope' => 1]))->toThrow(ViewException::class, "no public property 'nope'");
    });
});

describe('text and expressions', function (): void {
    it('reads text, mustaches, escapes and comments', function (string $markup, string $text): void {
        sharp("<Label>{$markup}</Label>", "public string \$who = 'Venus'; public ?string \$none = null;");
        expect(at('label1')->text())->toBe($text);
    })->with([
        ['  Hi   there  ', 'Hi there'],
        ['{{ $who }} rises', 'Venus rises'],
        ['{{ $who }} {{ $who }}', 'Venus Venus'],
        ['[{{ $none }}]', '[]'],
        ["{{ '}}' }}", '}}'],
        ["{{ ['a' => ['b' => 'deep']]['a']['b'] }}", 'deep'],
        ['@{{ $who }}', '{{ $who }}'],
        ['mail me@example.com', 'mail me@example.com'],
        ['@@if', '@if'],
        ['one{{-- <Button> @if --}}', 'one'],
        ['a < b > c', 'a < b > c'],
        ['it\'s "fine"', 'it\'s "fine"'],
        ['{{ strtoupper($who) }}', 'VENUS'],
        ['&lt;Card&gt;', '<Card>'],
    ]);

    it('puts {{ }} in a text attribute and PHP in a colon one', function (): void {
        sharp('<Column :spacing="$gap * 2"><Button label="Save {{ $gap }}" :minSize="[90, $gap]" :fill="$gap > 2" /></Column>', 'public int $gap = 4;');

        expect(at('column1.button1')->label())->toBe('Save 4')
            ->and(at('column1.button1')->calls)->toContain(['applyMinSize', [90, 4]])
            ->and(at('column1.button1')->calls)->toContain(['applyFill', [true, true]]);
    });

    it('runs @php and sees the imports, constants and private methods of @script', function (): void {
        sharp(<<<'S'
            <Column>
                @php
                    $total = array_sum(self::PARTS); // a note
                @endphp
                <Label :visible="$total === 6">{{ $this->shout((string) $total) }} {{ Align::END->value }}</Label>
            </Column>
            S, <<<'P'
            use Surface\Contracts\Toolkits\UI\Align;

            private const array PARTS = [1, 2, 3];
            private function shout(string $text): string { return "{$text}!"; }
            P);

        expect(at('column1.label1')->text())->toBe('6! end');
    });

    it('names a class that PHP or the imports would not allow', function (string $file): void {
        template($file, "@template\n<Label>ok</Label>\n@endtemplate");
        View::render($file);
        expect(at('label1')->text())->toBe('ok');
    })->with(['list', 'default', 'string', 'empty', '404', 'component', 'element', 'sharp-script']);
});

describe('handlers', function (): void {
    it('takes a method, a call with arguments, and a fn', function (): void {
        $view = sharp(<<<'S'
            <Column>
                <Button @click="one">a</Button>
                <Button @click="add($step, 100)">b</Button>
                <Button @click="fn () => $this->total = -1">c</Button>
                <Checkbox @toggle="toggled">d</Checkbox>
            </Column>
            S, <<<'P'
            public int $total = 0;
            public int $step = 5;
            public bool $on = false;
            private function one(): void { $this->total++; }
            public function add(int $by, int $more): void { $this->total += $by + $more; }
            public function toggled(bool $on): void { $this->on = $on; }
            P);

        click(at('column1.button1'));
        expect($view->total)->toBe(1);
        click(at('column1.button2'));
        expect($view->total)->toBe(106);
        click(at('column1.button3'));
        expect($view->total)->toBe(-1);

        at('column1.checkbox4')->nativeToggled(true);
        deliverPosted();
        expect($view->on)->toBeTrue();
    });

    it('gives each turn of a loop its own arguments', function (): void {
        $view = sharp(<<<'S'
            <Column>
                @foreach($items as $item)
                    <Button :key="$item" @click="remove($item)">{{ $item }}</Button>
                @endforeach
            </Column>
            S, <<<'P'
            public array $items = ['a', 'b', 'c'];
            public function remove(string $item): void { $this->items = array_values(array_diff($this->items, [$item])); }
            P);
        [$a, $b, $c] = screen()->children()[0]->children();

        click($b);

        expect($view->items)->toBe(['a', 'c'])
            ->and(screen()->children()[0]->children())->toBe([$a, $c]);
    });
});

describe('identity', function (): void {
    it('keeps what follows an @if while it comes and goes', function (): void {
        $view = sharp(<<<'S'
            <Column>
                @if($n === 1)
                    <Label>one</Label>
                @elseif($n === 2)
                    <Label>two</Label>
                    <Label>two again</Label>
                @else
                    <Label>other</Label>
                @endif
                <Button>after</Button>
            </Column>
            S, 'public int $n = 0;');
        $after = screen()->children()[0]->children()[1];

        foreach ([1 => ['one'], 2 => ['two', 'two again'], 3 => ['other']] as $n => $texts) {
            $view->n = $n;
            rerender();
            $children = screen()->children()[0]->children();

            expect(array_pop($children))->toBe($after)
                ->and(array_map(fn ($label) => $label->text(), $children))->toBe($texts);
        }
    });

    it('keeps what follows a keyed child that comes and goes', function (): void {
        $view = sharp('<Column>@if($show)<Label key="note">note</Label>@endif <Button>after</Button></Column>', 'public bool $show = false;');
        $after = at('column1.button1');

        $view->show = true;
        rerender();
        expect(screen()->children()[0]->children()[1])->toBe($after);

        $view->show = false;
        rerender();
        expect(screen()->children()[0]->children())->toBe([$after]);
    });

    it('moves a loop\'s children by key, and keeps what follows the loop', function (): void {
        $view = sharp(<<<'S'
            <Column>
                @foreach($items as $item)
                    <Label :key="$item">{{ $item }}</Label>
                    @if($item !== 'b')<Button :key="$item">{{ $item }}</Button>@endif
                @endforeach
                @foreach($items as $item)
                    <Label :key="$item">second {{ $item }}</Label>
                @endforeach
                <Toggle />
            </Column>
            S, "public array \$items = ['a', 'b'];");
        [$a, $button, $b, $second_a, $second_b, $last] = screen()->children()[0]->children();
        $built = built();

        $view->items = ['b', 'a'];
        rerender();

        expect(screen()->children()[0]->children())->toBe([$b, $a, $button, $second_b, $second_a, $last])
            ->and(built())->toBe($built);
    });

    it('lets inner loops repeat a key in each turn of the outer', function (): void {
        sharp(<<<'S'
            <Column>
                @foreach([[1, 2], [1, 2]] as $row)
                    @foreach($row as $cell)
                        <Label :key="$cell">{{ $cell }}</Label>
                    @endforeach
                @endforeach
            </Column>
            S);

        expect(array_map(fn ($label) => $label->text(), screen()->children()[0]->children()))->toBe(['1', '2', '1', '2']);
    });

    it('says which key is twice in a loop', function (): void {
        expect(fn () => sharp('<Column>@foreach([1, 1] as $i)<Label :key="$i">x</Label>@endforeach</Column>'))
            ->toThrow(ViewException::class, "have the key '1'");
    });
});

describe('tags', function (): void {
    it('uses a composed component, and a template inside that', function (): void {
        template('parts.badge', "@script\npublic string \$text = '';\n@endscript\n@template\n<Label>* {{ \$text }}</Label>\n@endtemplate");
        template('badge', "@script\npublic string \$text = '';\n@endscript\n@template\n<Label>[{{ \$text }}]</Label>\n@endtemplate");
        $view = sharp(<<<'S'
            <Column>
                <Notice title="Hi" width="90" badge="new" @closed="closed" align="center">
                    <Label>{{ $state }}</Label>
                </Notice>
            </Column>
            S, "public string \$state = 'open'; public function closed(): void { \$this->state = 'shut'; }");

        $notice = at('column1.notice1');
        [$title, $badge, $more, $close, $inside] = $notice->children();
        expect($title->text())->toBe('Hi:0')
            ->and($badge->text())->toBe('[new]')
            ->and($notice->calls)->toContain(['applyMinSize', [90, 0]])
            ->and($notice->calls)->toContain(['applyAlign', [Align::CENTER, Align::FILL]]);

        click($more);
        click($close);

        expect($view->state)->toBe('shut')
            ->and($title->text())->toBe('Hi:1')
            ->and($inside->text())->toBe('shut');
    });

    it('finds a kebab-case template anywhere under resources/, with its own state and slot', function (): void {
        template('parts.deep.photo-strip', <<<'S'
            @script
            public string $title = '';
            public int $seen = 0;
            @endscript
            @template
            <Column>
                <Button @click="fn () => $this->seen++">{{ $title }} {{ $seen }}</Button>
                <Children/>
            </Column>
            @endtemplate
            S);
        sharp('<Row><PhotoStrip title="A"><Label>in A</Label></PhotoStrip><PhotoStrip title="B" /></Row>');

        click(at('row1.photoStrip1.button1'));

        expect(at('row1.photoStrip1.button1')->label())->toBe('A 1')
            ->and(at('row1.photoStrip1.label2')->text())->toBe('in A')
            ->and(at('row1.photoStrip2.button1')->label())->toBe('B 0');
    });

    it('spells kebab-case as a developer would', function (string $tag, string $file): void {
        expect(TagResolver::kebab($tag))->toBe($file);
    })->with([['ImageCarousel', 'image-carousel'], ['H1', 'h1'], ['H1Title', 'h1-title'], ['HTMLView', 'html-view'], ['Card', 'card']]);

    it('lets a template use itself', function (): void {
        template('branch', <<<'S'
            @script
            public int $depth = 0;
            @endscript
            @template
            <Column>
                <Label>{{ $depth }}</Label>
                @if($depth > 0)<Branch :depth="$depth - 1" />@endif
            </Column>
            @endtemplate
            S);
        View::render('branch', ['depth' => 2]);

        expect(at('column1.branch2.branch2.label1')->text())->toBe('0');
    });

    it('keeps a built-in name whatever files there are', function (): void {
        template('label', "@template\n<Button>wrong</Button>\n@endtemplate");
        sharp('<Label>right</Label>');

        expect(at('label1')->text())->toBe('right');
    });
});

describe('what it refuses, with the line', function (): void {
    it('refuses', function (string $body, int $line, string $problem): void {
        template('broken', $body);

        try {
            View::render('broken');
        } catch (SharpCompileError $error) {
            expect($error->problem)->toContain($problem)
                ->and($error->templateLine)->toBe($line)
                ->and($error->template)->toBe('resources/broken.sharp.php');

            return;
        }
        $this->fail('It compiled.');
    })->with([
        'no template' => ["@script\n@endscript", 1, 'needs @template'],
        'an unclosed section' => ["\n@template\n<Column/>", 2, 'has no @endtemplate'],
        '@style' => ["@template\n<Column/>\n@endtemplate\n@style", 4, 'not supported yet'],
        'stray content' => ["hello\n@template\n<Column/>\n@endtemplate", 1, 'outside'],
        'script syntax' => ["@script\npublic int \$a = 0;\npublic int = 1;\n@endscript\n@template\n<Column/>\n@endtemplate", 3, 'does not parse'],
        'script render' => ["@script\n\npublic function render() {}\n@endscript\n@template\n<Column/>\n@endtemplate", 3, 'declares render()'],
        'an empty template' => ["@template\n@endtemplate", 2, 'empty'],
        'two roots' => ["@template\n<Column/>\n<Row/>\n@endtemplate", 3, 'one root'],
        'an unclosed tag' => ["@template\n<Column>\n<Row>\n</Row>\n@endtemplate", 2, 'never closed'],
        'the wrong close' => ["@template\n<Column>\n<Row>\n</Column>\n@endtemplate", 4, 'closes nothing'],
        'an unknown tag' => ["@template\n<Column>\n\n<Nope/>\n</Column>\n@endtemplate", 4, 'Unknown tag <Nope>'],
        'a lowercase tag' => ["@template\n<column/>\n@endtemplate", 2, '<Column>'],
        'an unknown attribute' => ["@template\n<Column\n  colour=\"red\"/>\n@endtemplate", 3, 'has no attribute colour'],
        'a component\'s unknown attribute' => ["@template\n<Notice titel=\"x\"/>\n@endtemplate", 2, 'has no attribute titel'],
        'a wrong type' => ["@template\n<Column spacing=\"wide\"/>\n@endtemplate", 2, "'wide' is not int"],
        'a wrong case' => ["@template\n<Column align=\"middle\"/>\n@endtemplate", 2, 'start, center, end, fill'],
        'a wrong colour' => ["@template\n<Column background=\"red\"/>\n@endtemplate", 2, 'not a colour'],
        'a wrong font' => ["@template\n<Label font=\"15 loud\">x</Label>\n@endtemplate", 2, 'neither a weight'],
        'too few values' => ["@template\n<Column minSize=\"5\"/>\n@endtemplate", 2, 'takes "width height"'],
        'a missing argument' => ["@template\n<Label/>\n@endtemplate", 2, 'needs text'],
        'text twice' => ["@template\n<Label text=\"a\">b</Label>\n@endtemplate", 2, 'both'],
        'loose text' => ["@template\n<Column>\nhello\n</Column>\n@endtemplate", 3, 'inside a <Label>'],
        'a loose mustache' => ["@template\n<Column>\n{{ 1 }}\n</Column>\n@endtemplate", 3, 'inside a <Label>'],
        'children in a leaf' => ["@template\n<Toggle>\n<Label>x</Label>\n</Toggle>\n@endtemplate", 2, 'holds nothing'],
        'a directive in text' => ["@template\n<Label>\n@if(true) x @endif</Label>\n@endtemplate", 3, 'only text'],
        'a missing method' => ["@template\n<Button\n @click=\"save\">x</Button>\n@endtemplate", 3, 'no method save()'],
        'an unknown event' => ["@template\n<Button @hover=\"save\">x</Button>\n@endtemplate", 2, 'it has @click'],
        'a changed copy' => ["@script\npublic int \$n = 0;\n@endscript\n@template\n<Button @click=\"fn () => \$n++\">x</Button>\n@endtemplate", 5, 'write $this->n'],
        'no key in a loop' => ["@template\n<Column>\n@foreach([1] as \$i)\n<Label>x</Label>\n@endforeach\n</Column>\n@endtemplate", 4, 'needs a key'],
        'a bad expression' => ["@template\n<Column>\n<Label>{{ 1 + }}</Label>\n</Column>\n@endtemplate", 3, 'not a PHP expression'],
        'a bad foreach' => ["@template\n<Column>\n@foreach(\$x)\n@endforeach\n</Column>\n@endtemplate", 3, 'not a foreach'],
        'an open if' => ["@template\n<Column>\n@if(true)\n<Row/>\n</Column>\n@endtemplate", 5, 'still open'],
        'an open mustache' => ["@template\n<Label>{{ 1</Label>\n@endtemplate", 2, 'no }}'],
        'an open comment' => ["@template\n<Column/>\n{{-- oops\n@endtemplate", 3, 'no --}}'],
        'a PHP tag' => ["@template\n<Column>\n<?php echo 1 ?>\n</Column>\n@endtemplate", 3, '@php'],
        'a slot as root' => ["@template\n<Children/>\n@endtemplate", 2, 'cannot be the root'],
    ]);

    it('refuses a tag that two files claim', function (): void {
        template('a.chip', "@template\n<Row/>\n@endtemplate");
        template('b.chip', "@template\n<Row/>\n@endtemplate");

        expect(fn () => sharp('<Chip/>'))->toThrow(SharpCompileError::class, 'rename all but one');
    });

    it('refuses a tag that a class and a file claim', function (): void {
        template('notice', "@template\n<Row/>\n@endtemplate");

        expect(fn () => sharp('<Notice/>'))->toThrow(SharpCompileError::class, 'rename all but one');
    });

    it('blames a used template for its own mistake', function (): void {
        template('child', "@template\n<Column>\n<Nope/>\n</Column>\n@endtemplate");

        expect(fn () => sharp('<Child/>'))->toThrow(SharpCompileError::class, 'resources/child.sharp.php:3');
    });

    it('leaves the view on screen when the next does not compile', function (): void {
        sharp('<Label>still here</Label>');
        template('broken', "@template\n<Nope/>\n@endtemplate");

        expect(fn () => View::render('broken'))->toThrow(SharpCompileError::class)
            ->and(at('label1')->text())->toBe('still here');
    });

    it('gives a runtime error the template\'s line', function (): void {
        template('runtime', "@template\n<Column>\n\n\n<Label>{{ intdiv(1, 0) }}</Label>\n</Column>\n@endtemplate");

        try {
            View::render('runtime');
        } catch (DivisionByZeroError $error) {
            $frame = $error->getTrace()[0];
            expect($frame['line'])->toBe(5)->and(basename($frame['file']))->toStartWith('sharp-runtime-');

            return;
        }
        $this->fail('It rendered.');
    });

    it('says what it looked for, and reads no path outside resources/', function (): void {
        expect(fn () => View::render('missing.view'))->toThrow(ViewException::class, 'resources/missing/view.sharp.php')
            ->and(fn () => View::render('../secrets'))->toThrow(ViewException::class, 'No view');
    });
});

describe('the cache', function (): void {
    it('keeps the class while the files are unchanged, in a new process too', function (): void {
        $first = sharp('<Label>a</Label>')::class;
        $files = glob(test()->root.'/cache/sharp-*.json');
        touch($files[0], 1000);

        expect(newSharp()->view('page'))->toBe($first);
        clearstatcache();
        expect(filemtime($files[0]))->toBe(1000);
    });

    it('shows an edit at the next render', function (): void {
        sharp('<Label>before</Label>');
        sharp('<Label>after</Label>');

        expect(at('label1')->text())->toBe('after');
    });

    it('compiles again when a used template loses a prop', function (): void {
        template('chip', "@script\npublic string \$text = '';\n@endscript\n@template\n<Row/>\n@endtemplate");
        sharp('<Chip text="x"/>');
        template('chip', "@template\n<Row/>\n@endtemplate");

        expect(fn () => View::render('page'))->toThrow(SharpCompileError::class, 'has no attribute text');
    });

    it('compiles again when a tag comes to mean two things', function (): void {
        sharp('<Notice title="x"/>');
        template('notice', "@template\n<Row/>\n@endtemplate");

        expect(fn () => View::render('page'))->toThrow(SharpCompileError::class, 'rename all but one');
    });

    it('reads no file when a click renders again', function (): void {
        template('chip', "@script\npublic int \$n = 0;\n@endscript\n@template\n<Label>{{ \$n }}</Label>\n@endtemplate");
        $view = sharp('<Column>@if($n > 0)<Chip :n="$n"/>@endif<Button @click="fn () => $this->n++">+</Button></Column>', 'public int $n = 0;');

        // The template is gone before the first render that shows it.
        unlink(test()->root.'/resources/chip.sharp.php');
        unlink(test()->root.'/resources/page.sharp.php');
        click(at('column1.button1'));

        expect($view->n)->toBe(1)
            ->and(screen()->children()[0]->children()[0]->text())->toBe('1');
    });

    it('compiles every template for sharp:cache', function (): void {
        template('one', "@template\n<Row/>\n@endtemplate");
        template('two.three', "@template\n<Row/>\n@endtemplate");

        expect(newSharp()->compileAll())->toEqualCanonicalizing(['one.sharp.php', 'two/three.sharp.php']);
    });

    it('renders and patches 500 compiled labels quickly', function (): void {
        $view = sharp('<Column>@foreach(range(1, 500) as $i)<Label :key="$i">{{ $i }}:{{ $n }}</Label>@endforeach</Column>', 'public int $n = 0;');
        $view->n = 1;
        $start = hrtime(true);
        rerender();
        $milliseconds = (hrtime(true) - $start) / 1e6;
        fwrite(STDERR, sprintf("\n  sharp: re-render and patch of 500 labels %.2f ms\n", $milliseconds));

        expect($milliseconds)->toBeLessThan(50.0);
    });
});
