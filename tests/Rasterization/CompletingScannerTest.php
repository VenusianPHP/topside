<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Framebuffers\Spans;
use Surface\Contracts\Rendering\Rasterization\CompleteScanner;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Rendering\Rasterization\RasterizeException;
use Surface\Contracts\Rendering\Rasterization\Scanner;
use Surface\VelvetGE\Rasterize\CompletingScanner;
use Surface\VelvetGE\Rasterize\PHPLandScanner;
use Venusian\Topside\Tests\Fixtures\CoreOnlyScanner;
use Venusian\Topside\Tests\Fixtures\PartialRasterizationDriver;
use Venusian\Topside\Tests\Fixtures\ShapesAndBatchScanner;
use Venusian\Topside\Tests\Support\Rasterization\SpanGrid;

// A library scanner may leave tiers out. Completed, it has every op, and each op goes to the scanner that has it.

/** One op of every kind, as Scanner::batch() takes them. */
function everyOp(): array
{
    return [
        [Scanner::PATH, [[2.5, 2.5, 20.25, 4.0, 11.0, 18.75]], 0],
        [Scanner::ELLIPSE, 12.0, 12.0, 9.5, 6.25],
        [Scanner::RING, 12.0, 12.0, 9.0, 7.0, 2.5],
        [Scanner::ROUND_RECT, 2.0, 3.0, 21.5, 19.0, [4.0, 2.0, 6.0, 0.0]],
        [Scanner::ROUND_RING, 2.0, 3.0, 21.5, 19.0, [4.0, 2.0, 6.0, 0.0], 2.0],
        [Scanner::SHADOW, 5.0, 5.0, 18.0, 17.0, 3.0, 4.0],
        [Scanner::POLYLINE, [1.0, 1.0, 20.0, 9.0, 4.0, 21.0], true],
    ];
}

it('leaves a complete scanner as it is', function (): void {
    $scanner = new PHPLandScanner(new Region(0, 0, 24, 24), Edges::ANTIALIASED);

    expect(CompletingScanner::over($scanner))->toBe($scanner);
});

it('completes a scanner with the same spans a complete one answers', function (string $class, Edges $edges): void {
    $clip = new Region(0, 0, 24, 24);
    $complete = new PHPLandScanner($clip, $edges);
    $completed = CompletingScanner::over(new $class($clip, $edges));

    expect($completed)->toBeInstanceOf(CompleteScanner::class)
        ->and($completed->clip())->toEqual($clip)
        ->and($completed->edges())->toBe($edges)
        ->and($completed->batch(everyOp()))->toBe($complete->batch(everyOp()))
        ->and($completed->shadow(5.0, 5.0, 18.0, 17.0, 3.0, 4.0))->toBe($complete->shadow(5.0, 5.0, 18.0, 17.0, 3.0, 4.0))
        ->and($completed->polyline([1.0, 1.0, 20.0, 9.0], false))->toBe($complete->polyline([1.0, 1.0, 20.0, 9.0], false))
        ->and($completed->ellipse(12.0, 12.0, 9.5, 6.25))->toBe($complete->ellipse(12.0, 12.0, 9.5, 6.25))
        ->and($completed->path([[2.5, 2.5, 20.25, 4.0, 11.0, 18.75]], FillRule::EVEN_ODD))->toBe($complete->path([[2.5, 2.5, 20.25, 4.0, 11.0, 18.75]], FillRule::EVEN_ODD));
})->with([CoreOnlyScanner::class, ShapesAndBatchScanner::class])->with([Edges::ANTIALIASED, Edges::HARD]);

it('asks the scanner only for the ops it has', function (): void {
    $clip = new Region(0, 0, 24, 24);

    CoreOnlyScanner::$asked = [];
    CompletingScanner::over(new CoreOnlyScanner($clip, Edges::ANTIALIASED))->batch(everyOp());
    expect(CoreOnlyScanner::$asked)->toBe(['path']);

    CoreOnlyScanner::$asked = [];
    CompletingScanner::over(new ShapesAndBatchScanner($clip, Edges::ANTIALIASED))->batch(everyOp());
    expect(CoreOnlyScanner::$asked)->toBe(['path', 'ellipse', 'ring', 'roundRect', 'roundRing']);
});

it('hands a batch to the scanner whole when the scanner has every op in it', function (): void {
    CoreOnlyScanner::$asked = [];
    CompletingScanner::over(new ShapesAndBatchScanner(new Region(0, 0, 24, 24), Edges::ANTIALIASED))->batch(array_slice(everyOp(), 0, 5));

    expect(CoreOnlyScanner::$asked)->toBe(['batch']);
});

it('answers nothing for a batch with a bad op', function (): void {
    $completed = CompletingScanner::over(new CoreOnlyScanner(new Region(0, 0, 24, 24), Edges::ANTIALIASED));

    expect(fn () => $completed->batch([everyOp()[0], [99]]))->toThrow(RasterizeException::class)
        ->and(fn () => $completed->batch(['nope']))->toThrow(RasterizeException::class);
});

it('names the driver that minted a rasterizer', function (): void {
    $driver = new PartialRasterizationDriver('core-only', CoreOnlyScanner::class);
    $rasterizer = $driver->rasterizer(new Region(0, 0, 24, 24), Edges::ANTIALIASED);

    expect($rasterizer->driver())->toBe('core-only')
        ->and($rasterizer->fillEllipse(12.0, 12.0, 6.0, 6.0))->not->toBe('');
});

it('measures how far two span lists are apart, pixel by pixel', function (): void {
    $a = Spans::pack(0, 0, 4, 200).Spans::pack(1, 2, 1, 10);
    $b = Spans::pack(0, 0, 2, 198).Spans::pack(0, 2, 2, 200);

    expect(SpanGrid::distance($a, $a))->toBe(0)
        ->and(SpanGrid::distance($a, $b))->toBe(10)
        ->and(SpanGrid::distance($a, ''))->toBe(200);
});
