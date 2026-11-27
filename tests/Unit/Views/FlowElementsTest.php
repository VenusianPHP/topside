<?php

declare(strict_types=1);

use Surface\Toolkits\UI\TKFlow;
use Surface\Contracts\Toolkits\UI\Insets;

/*
| Flow: children side by side at their own size, wrapping where the width runs out. The
| wrapping is the toolkit's; topside keeps the children, their order, spacing and padding.
*/
beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});

afterEach(fn () => dropSharpApp());

it('builds a flow with its spacing and padding, its children in order', function (): void {
    sharp(<<<'S'
        <Flow spacing="8" padding="4 0">
            @foreach ($tags as $tag)
                <Label :key="$tag">{{ $tag }}</Label>
            @endforeach
        </Flow>
        S, <<<'S'
        public array $tags = ['Jupiter', 'Voyager', 'Juno'];
        S);

    $flow = at('flow1');

    expect($flow)->toBeInstanceOf(TKFlow::class)
        ->and($flow->spacing())->toBe(8)
        ->and($flow->padding())->toEqual(Insets::of('4 0'))
        ->and(array_map(static fn ($child): string => $child->text(), $flow->children()))->toBe(['Jupiter', 'Voyager', 'Juno']);
});

it('changes spacing and padding in place, and reorders keyed children without rebuilding them', function (): void {
    sharp(<<<'S'
        <Column>
            <Flow :spacing="$gap" :padding="$pad">
                @foreach ($tags as $tag)
                    <Label :key="$tag">{{ $tag }}</Label>
                @endforeach
            </Flow>
            <Button @click="change">change</Button>
        </Column>
        S, <<<'S'
        public array $tags = ['a', 'b', 'c'];
        public int $gap = 8;
        public int $pad = 0;

        public function change(): void
        {
            $this->tags = ['c', 'a', 'b'];
            $this->gap = 12;
            $this->pad = 6;
        }
        S);

    $flow = at('column1.flow1');
    $built = built();
    click(at('column1.button2'));

    expect(at('column1.flow1'))->toBe($flow)
        ->and(built())->toBe($built)
        ->and($flow->spacing())->toBe(12)
        ->and($flow->padding())->toEqual(Insets::of(6))
        ->and(array_map(static fn ($child): string => $child->text(), $flow->children()))->toBe(['c', 'a', 'b']);
});
