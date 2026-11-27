<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\Mail\Views\ViewResized;

/*
| A view reports its size: while it watches, each new size the toolkit lays it out at is posted
| once, the latest per pump, and `@resized` hands it to the page.
*/
beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});
afterEach(function (): void {
    dropSharpApp();
});

/** The ViewResized mail the session holds, after the pump's flush. */
function resizesPosted(): array
{
    flushDrags();
    [$posted, test()->loop->posted] = [test()->loop->posted, []];

    return array_values(array_map(
        static fn (ViewResized $mail): array => [$mail->path, $mail->width, $mail->height],
        array_filter($posted, static fn (object $mail): bool => $mail instanceof ViewResized),
    ));
}

describe('the primitive', function (): void {
    it('tells the toolkit to watch, and to stop, only on a change', function (): void {
        $column = screen()->column('frame');
        $column->watchSize()->watchSize()->watchSize(false);

        expect(array_values(array_filter($column->calls, static fn (array $call): bool => $call[0] === 'applyWatchSize')))
            ->toBe([['applyWatchSize', [true]], ['applyWatchSize', [false]]])
            ->and($column->isWatchingSize())->toBeFalse();
    });

    it('posts each new size once, the latest per pump', function (): void {
        $column = screen()->column('frame')->watchSize();
        $column->nativeResized(400, 300);
        $column->nativeResized(500, 300);
        $first = resizesPosted();
        $column->nativeResized(500, 300);
        $same = resizesPosted();
        $column->nativeResized(640, 480);

        expect($first)->toBe([['root.frame', 500, 300]])
            ->and($same)->toBe([])
            ->and(resizesPosted())->toBe([['root.frame', 640, 480]]);
    });

    it('posts nothing while not watching, nor a size with no width or no height', function (): void {
        $column = screen()->column('frame');
        $column->nativeResized(400, 300);
        $column->watchSize();
        $column->nativeResized(0, 300);
        $column->nativeResized(400, 0);

        expect(resizesPosted())->toBe([]);
    });

    it('posts the size again after watching is turned off and on', function (): void {
        $column = screen()->column('frame')->watchSize();
        $column->nativeResized(400, 300);
        resizesPosted();
        $column->watchSize(false)->watchSize();
        $column->nativeResized(400, 300);

        expect(resizesPosted())->toBe([['root.frame', 400, 300]]);
    });
});

describe('in Sharp', function (): void {
    it('runs @resized with the width and height, and watches only while it has the handler', function (): void {
        sharp(<<<'S'
            <Column>
                @if ($watching)
                    <Column key="frame" @resized="fit" />
                @else
                    <Column key="frame" />
                @endif
                <Button @click="stop">stop</Button>
                <Label>{{ $said }}</Label>
            </Column>
            S, <<<'S'
            public bool $watching = true;
            public string $said = '';

            public function fit(int $width, int $height): void
            {
                $this->said = "{$width}x{$height}";
            }

            public function stop(): void
            {
                $this->watching = false;
            }
            S);

        $frame = at('column1.column1');
        $frame->nativeResized(780, 512);
        deliverPosted();
        $said = at('column1.label3')->text();

        click(at('column1.button2'));

        expect($said)->toBe('780x512')
            ->and($frame->isWatchingSize())->toBeFalse();
    });
});
