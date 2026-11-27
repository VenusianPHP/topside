<?php

declare(strict_types=1);

use Surface\Toolkits\Presentation;

/*
| A label that truncates is one line, cut at the end with "…" where the text is wider than its
| room, and asks for no more width than that room. The toolkit is told through
| applyTruncate(); the label keeps both truncate and wrap, for an adapter to read.
*/
beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});
afterEach(fn () => dropSharpApp());

/** The arguments of each time $hook was told to $primitive, oldest first. */
function truncateToldWith(object $primitive, string $hook): array
{
    return array_values(array_map(static fn (array $call): array => $call[1], array_filter($primitive->calls, static fn (array $call): bool => $call[0] === $hook)));
}

it('tells the toolkit, and keeps truncate and wrap for the adapter to read', function (): void {
    $label = screen()->label('title', 'A very long title');
    $label->setWrap(true)->setTruncate(true);

    expect(truncateToldWith($label, 'applyTruncate'))->toBe([[true]])
        ->and($label->truncates())->toBeTrue()
        ->and($label->wraps())->toBeTrue();

    $label->setTruncate(false);

    expect(truncateToldWith($label, 'applyTruncate'))->toBe([[true], [false]])
        ->and($label->truncates())->toBeFalse()
        ->and($label->wraps())->toBeTrue();
});

it('truncates from <Label truncate> in Sharp, and stops when it is taken away', function (): void {
    $view = sharp('<Label :truncate="$cut" wrap>{{ $title }}</Label>', 'public bool $cut = true; public string $title = "A very long title";');
    $label = at('label1');

    expect(truncateToldWith($label, 'applyTruncate'))->toBe([[true]])
        ->and($label->truncates())->toBeTrue()
        ->and($label->wraps())->toBeTrue();

    $view->cut = false;
    Presentation::driver()->view()->render();

    expect(truncateToldWith($label, 'applyTruncate'))->toBe([[true], [false]]);
});

it('takes truncate alone as true', function (): void {
    sharp('<Label truncate>A very long title</Label>');

    expect(at('label1')->truncates())->toBeTrue();
});
