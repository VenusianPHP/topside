<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Rasterization\CLandRasterizationDriver;
use Venusian\Topside\Tests\Fixtures\CoreOnlyScanner;
use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\VelvetGE\Rasterize\CLandScanner;
use Venusian\Topside\Tests\Fixtures\DirectPathScanner;
use Venusian\Topside\Tests\Fixtures\MemoryPathScanner;
use Venusian\Topside\Tests\Fixtures\MemoryPixelStore;
use Venusian\Topside\Tests\Fixtures\PartialFramebufferDriver;
use Venusian\Topside\Tests\Fixtures\PartialRasterizationDriver;
use Venusian\Topside\Tests\Support\Framebuffers\Formats;

/*
 * Direct fill: the extended rasterizer paints a batch straight into the framebuffer's FbBuffer through ext-fb's span
 * sink. The pixels and the damage are those of the spans path, on every kind of framebuffer that has a sink.
 */

/** @return list<array<int, mixed>> */
function directShapes(): array
{
    $clip = new Region(0, 0, 40, 30);

    return [
        ['path', [[[1.5, 2.0], [30.2, 4.1], [9.0, 25.7]], [[5.0, 5.0], [12.0, 5.0], [12.0, 12.0]]], FillRule::EVEN_ODD, 0x3366CCB0, $clip],
        ['polyline', [[2.0, 27.0], [37.0, 20.0]], 3.5, false, 0x00FF0080, $clip],
        ['ellipse', 20.25, 15.0, 9.5, 6.0, 0xFFFFFF60, $clip],
        ['ring', 20.0, 15.5, 9.0, 7.0, 2.5, 0x0000FFFF, $clip],
        ['roundrect', 2.4, 3.0, 33.1, 26.0, [4.0, 0.0, 6.5, 2.0], 0x80808040, $clip],
        ['roundring', 2.4, 3.0, 33.1, 26.0, [4.0, 0.0, 6.5, 2.0], 1.75, 0x10EE10D0, $clip],
        ['shadow', 6.0, 7.0, 30.0, 22.0, 3.0, 2.5, 0x000000A0, $clip],
        ['ellipse', 500.0, 500.0, 3.0, 3.0, 0xFFFFFFFF, $clip],
    ];
}

it('paints and damages as the spans path does, on every kind with a sink', function (FormatSpec $spec, string $kind, Edges $edges): void {
    $driver = new CLandFramebufferDriver;
    $make = fn (): Framebuffer => match ($kind) {
        'full' => $driver->full($spec, 40, 30),
        'dirty' => $driver->dirty($spec, 40, 30),
        'ring' => $driver->ring($spec, 40, 30, 2),
    };
    [$direct, $spanned] = [$make(), $make()];
    $kind === 'dirty' && $direct->beginEpoch() && $spanned->beginEpoch();
    $raster = (new CLandRasterizationDriver)->rasterizer(new Region(3, 2, 34, 25), $edges);

    $raster->fillBatch(directShapes(), $direct);
    $raster->fillBatch(directShapes(), $spanned, false);

    expect($direct->spanSink())->toBeInstanceOf(FbBuffer::class)
        ->and(bin2hex($direct->dump()))->toBe(bin2hex($spanned->dump()));
    $kind === 'dirty' && expect($direct->damage())->toEqual($spanned->damage());
})->with([FormatSpec::rgba8(), Formats::rgb565()])->with(['full', 'dirty', 'ring'])->with([Edges::ANTIALIASED, Edges::HARD])
    ->skip(! class_exists(RasterScanner::class) || ! RasterScanner::fills(), 'ext-rasterize with ext-fb\'s span sink is not loaded in this PHP.');

it('has no sink where spans must go through paintSpans()', function (): void {
    expect((new PHPLandFramebufferDriver)->full(FormatSpec::rgba8(), 4, 4)->spanSink())->toBeNull()
        ->and((new PHPLandFramebufferDriver)->paged(FormatSpec::rgba8(), 4, 8, 4)->spanSink())->toBeNull();
});

it('falls back to the spans path into a framebuffer with no sink', function (): void {
    [$direct, $spanned] = [(new PHPLandFramebufferDriver)->full(FormatSpec::rgba8(), 40, 30), (new PHPLandFramebufferDriver)->full(FormatSpec::rgba8(), 40, 30)];
    $raster = (new CLandRasterizationDriver)->rasterizer(new Region(0, 0, 40, 30), Edges::ANTIALIASED);

    $raster->fillBatch(directShapes(), $direct);
    $raster->fillBatch(directShapes(), $spanned, false);

    expect(bin2hex($direct->dump()))->toBe(bin2hex($spanned->dump()));
})->skip(! class_exists(RasterScanner::class), 'ext-rasterize is not loaded in this PHP.');

/*
 * A library scanner may paint direct too. It is given only the ops it has; the rest of the batch goes through
 * spans, in the order the shapes came.
 */

it('paints a library scanner\'s own shapes direct and the rest through spans, in order', function (FormatSpec $spec, string $kind, Edges $edges): void {
    $driver = new CLandFramebufferDriver;
    $make = fn (): Framebuffer => match ($kind) {
        'full' => $driver->full($spec, 40, 30),
        'dirty' => $driver->dirty($spec, 40, 30),
    };
    [$direct, $spanned, $velvet] = [$make(), $make(), $make()];
    $kind === 'dirty' && $direct->beginEpoch() && $spanned->beginEpoch() && $velvet->beginEpoch();
    $clip = new Region(3, 2, 34, 25);
    $shapes = [...directShapes(), ['path', [[[4.0, 4.0], [36.0, 6.0], [20.0, 26.0]]], FillRule::NON_ZERO, 0xCC220090, $clip]];
    $raster = (new PartialRasterizationDriver('direct-path', DirectPathScanner::class))->rasterizer($clip, $edges);

    CoreOnlyScanner::$asked = [];
    $raster->fillBatch($shapes, $direct);
    $asked = CoreOnlyScanner::$asked;
    $raster->fillBatch($shapes, $spanned, false);
    (new CLandRasterizationDriver)->rasterizer($clip, $edges)->fillBatch($shapes, $velvet, false);

    // the path and the wide polyline (a path once lowered) direct, five shapes through spans, the last path direct
    expect($asked)->toBe(['fill:2', 'fill:1'])
        ->and(bin2hex($direct->dump()))->toBe(bin2hex($spanned->dump()))
        ->and(bin2hex($direct->dump()))->toBe(bin2hex($velvet->dump()));
    $kind === 'dirty' && expect($direct->damage())->toEqual($spanned->damage());
})->with([FormatSpec::rgba8(), Formats::rgb565()])->with(['full', 'dirty'])->with([Edges::ANTIALIASED, Edges::HARD])
    ->skip(! class_exists(RasterScanner::class) || ! RasterScanner::fills(), 'ext-rasterize with ext-fb\'s span sink is not loaded in this PHP.');

it('sends a library scanner\'s batch through spans when it cannot paint into the sink', function (): void {
    [$direct, $spanned] = [(new PHPLandFramebufferDriver)->full(FormatSpec::rgba8(), 40, 30), (new PHPLandFramebufferDriver)->full(FormatSpec::rgba8(), 40, 30)];
    $raster = (new PartialRasterizationDriver('direct-path', DirectPathScanner::class))->rasterizer(new Region(0, 0, 40, 30), Edges::ANTIALIASED);

    CoreOnlyScanner::$asked = [];
    $raster->fillBatch(directShapes(), $direct);
    $asked = CoreOnlyScanner::$asked;
    $raster->fillBatch(directShapes(), $spanned, false);

    expect($asked)->not->toContain('fill:2')
        ->and(bin2hex($direct->dump()))->toBe(bin2hex($spanned->dump()));
});

/*
 * The scanner and the store need not know each other. A store may only say where its bytes are (a PixelMemory),
 * and a scanner may take only that: VelvetGE brings the two together.
 */

$bridged = fn (): bool => ! class_exists(RasterScanner::class) || ! RasterScanner::fills() || ! method_exists(FbBuffer::class, 'over');

it('paints ext-rasterize straight into a library store that only says where its bytes are', function (FormatSpec $spec, string $kind, Edges $edges): void {
    $library = new PartialFramebufferDriver('memory', MemoryPixelStore::class);
    $make = fn ($driver): Framebuffer => $kind === 'full' ? $driver->full($spec, 40, 30) : $driver->dirty($spec, 40, 30);
    [$direct, $spanned, $velvet] = [$make($library), $make($library), $make(new CLandFramebufferDriver)];
    $kind === 'dirty' && $direct->beginEpoch() && $spanned->beginEpoch() && $velvet->beginEpoch();
    $raster = (new CLandRasterizationDriver)->rasterizer(new Region(3, 2, 34, 25), $edges);

    MemoryPixelStore::$loads = 0;
    $raster->fillBatch(directShapes(), $direct);
    $loads = MemoryPixelStore::$loads;
    $raster->fillBatch(directShapes(), $spanned, false);
    $raster->fillBatch(directShapes(), $velvet);

    // direct: the store's pixels were never lent out and loaded back; through spans they were, once a shape
    expect($direct->spanSink())->toBeInstanceOf(PixelMemory::class)
        ->and($loads)->toBe(0)
        ->and(MemoryPixelStore::$loads)->toBeGreaterThan(0)
        ->and(bin2hex($direct->dump()))->toBe(bin2hex($spanned->dump()))
        ->and(bin2hex($direct->dump()))->toBe(bin2hex($velvet->dump()));
    $kind === 'dirty' && expect($direct->damage())->toEqual($velvet->damage());
})->with([FormatSpec::rgba8(), Formats::rgb565()])->with(['full', 'dirty'])->with([Edges::ANTIALIASED, Edges::HARD])
    ->skip($bridged, 'ext-rasterize with ext-fb\'s span sink and FbBuffer::over() are not loaded in this PHP.');

it('paints a library scanner that takes only memory straight into ext-fb\'s store', function (Edges $edges): void {
    $driver = new CLandFramebufferDriver;
    [$direct, $spanned] = [$driver->dirty(FormatSpec::rgba8(), 40, 30), $driver->dirty(FormatSpec::rgba8(), 40, 30)];
    $direct->beginEpoch() && $spanned->beginEpoch();
    $raster = (new PartialRasterizationDriver('memory-path', MemoryPathScanner::class))->rasterizer(new Region(3, 2, 34, 25), $edges);

    CoreOnlyScanner::$asked = [];
    $raster->fillBatch(directShapes(), $direct);
    $asked = CoreOnlyScanner::$asked;
    $raster->fillBatch(directShapes(), $spanned, false);

    expect($asked)->toBe(['fill:2'])
        ->and(bin2hex($direct->dump()))->toBe(bin2hex($spanned->dump()))
        ->and($direct->damage())->toEqual($spanned->damage());
})->with([Edges::ANTIALIASED, Edges::HARD])->skip($bridged, 'ext-rasterize with ext-fb\'s span sink and FbBuffer::over() are not loaded in this PHP.');

it('leaves memory ext-fb cannot stand over to the spans path', function (): void {
    $owner = new FbBuffer(new FbFormat(FB_LAYOUT_RGBA8888, channelOrder: FB_CHANNELS_RGBA), 8, 4);
    $scanner = new CLandScanner(new Region(0, 0, 8, 4), Edges::ANTIALIASED);

    expect($scanner->paintsInto(new PixelMemory($owner->pointer(), 8, 4, 32, FormatSpec::rgba8(), $owner)))->toBeTrue()
        ->and($scanner->paintsInto(new PixelMemory($owner->pointer(), 8, 4, 36, FormatSpec::rgba8(), $owner)))->toBeFalse()   // padded rows
        ->and($scanner->paintsInto(new stdClass))->toBeFalse();
})->skip($bridged, 'ext-rasterize with ext-fb\'s span sink and FbBuffer::over() are not loaded in this PHP.');
