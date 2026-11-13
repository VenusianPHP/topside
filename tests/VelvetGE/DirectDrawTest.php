<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\BitDepth;
use Surface\Contracts\Rendering\Framebuffers\ChannelOrder;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\FramebufferDriver;
use Surface\Contracts\Rendering\Framebuffers\FramebufferException;
use Surface\Contracts\Rendering\Framebuffers\PixelFormat;
use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Rendering\Rasterization\RasterizeDriver;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Scenery;
use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Rasterization\CLandRasterizationDriver;
use Surface\VelvetGE\Drivers\Raycasting\CLandRaycastingDriver;
use Surface\VelvetGE\Rasterize\CLandScanner;
use Venusian\Topside\Tests\Fixtures\CoreOnlyScanner;
use Venusian\Topside\Tests\Fixtures\DirectPathScanner;
use Venusian\Topside\Tests\Fixtures\LibraryMarcher;
use Venusian\Topside\Tests\Fixtures\LibraryRaycastingDriver;
use Venusian\Topside\Tests\Fixtures\MaskPathScanner;
use Venusian\Topside\Tests\Fixtures\MemoryPixelStore;
use Venusian\Topside\Tests\Fixtures\PartialFramebufferDriver;
use Venusian\Topside\Tests\Fixtures\PartialRasterizationDriver;
use Venusian\Topside\Tests\Support\Framebuffers\Formats;
use Venusian\Topside\Tests\Support\Framebuffers\Tolerance;

/*
 * VelvetGE's DirectDraw is the one place a producer (a rasterizer's scanner, a raycaster's marcher) meets a
 * pixel store. These hold it to one promise: whichever producer draws on whichever store, in whatever format,
 * the pixels and the damage are those of the plain path (spans, or a picture written as RGBA8). A library's
 * driver joins by being in the datasets.
 */

/** One shape of every kind, a wide polyline (a path once lowered), one that falls outside, and a last path. */
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
        ['path', [[[4.0, 4.0], [36.0, 6.0], [20.0, 26.0]]], FillRule::NON_ZERO, 0xCC220090, $clip],
    ];
}

function argb8(): FormatSpec
{
    return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B32, channel_order: ChannelOrder::ARGB);
}

/** How far apart two dumps are, byte by byte. */
function dumpDistance(string $a, string $b): int
{
    expect(strlen($a))->toBe(strlen($b));
    $worst = 0;
    for ($i = 0, $n = strlen($a); $i < $n; $i++) {
        $worst = max($worst, abs(ord($a[$i]) - ord($b[$i])));
    }

    return $worst;
}

$room = fn (): Grid => Grid::of([[1, 1, 1, 1, 1], [1, 0, 0, 0, 1], [1, 0, 0, 0, 1], [1, 0, 0, 0, 2], [1, 3, 1, 1, 1]]);
$scenery = fn (): Scenery => new Scenery([1 => 0xC04020FF, 2 => 0x2040C0FF, 3 => 0x20C040FF], 0x111111FF, 0x222222FF, 160);
$accelerated = fn (): bool => ! class_exists(RasterScanner::class) || ! RasterScanner::fills() || ! method_exists(FbBuffer::class, 'over') || ! CLandRaycastingDriver::available();
const NEEDS_EXTENSIONS = 'ext-fb, ext-rasterize (with ext-fb\'s span sink) and ext-raycast are not all loaded in this PHP.';

// The matrix: every rasterization driver on every framebuffer driver.

it('paints a batch as the spans path does, whichever scanner and whichever store', function (RasterizeDriver $rasterizers, FramebufferDriver $frames, FormatSpec $spec, Edges $edges): void {
    [$direct, $spanned] = [$frames->dirty($spec, 40, 30), $frames->dirty($spec, 40, 30)];
    $direct->beginEpoch() && $spanned->beginEpoch();
    $raster = $rasterizers->rasterizer(new Region(3, 2, 34, 25), $edges);

    $raster->fillBatch(directShapes(), $direct);
    $raster->fillBatch(directShapes(), $spanned, false);

    // the same bytes; within its own tolerance on a library store that blends spans with its own arithmetic
    expect(dumpDistance($direct->dump(), $spanned->dump()))->toBeLessThanOrEqual(Tolerance::of($frames))
        ->and($direct->damage())->toEqual($spanned->damage());
})->with('every rasterization driver')->with('framebuffer drivers')->with([FormatSpec::rgba8(), FormatSpec::bgra8(), Formats::rgb565()])->with([Edges::ANTIALIASED, Edges::HARD]);

// Which way each pairing went. The matrix above says the pixels are right; these say the fast way was taken.

it('says where the pixels are for every store outside PHP, and nowhere for the rest', function (): void {
    $php = new PHPLandFramebufferDriver;

    expect($php->full(FormatSpec::rgba8(), 4, 4)->memory())->toBeNull()
        ->and($php->paged(FormatSpec::rgba8(), 4, 8, 4)->memory())->toBeNull()
        ->and(fn () => $php->full(FormatSpec::rgba8(), 4, 4)->drewInto(new Region(0, 0, 1, 1)))->toThrow(FramebufferException::class, 'no memory')
        ->and(fn () => $php->paged(FormatSpec::rgba8(), 4, 8, 4)->drewInto(new Region(0, 0, 1, 1)))->toThrow(FramebufferException::class, 'no memory');
});

it('describes an ext-fb store\'s memory without naming ext-fb to the caller', function (FormatSpec $spec, int $stride): void {
    $frames = new CLandFramebufferDriver;
    $memory = $frames->full($spec, 10, 4)->memory();
    $ring = $frames->ring($spec, 10, 4, 2);

    expect($memory)->toBeInstanceOf(PixelMemory::class)
        ->and([$memory->width, $memory->height, $memory->stride])->toBe([10, 4, $stride])
        ->and($memory->format->equals($spec))->toBeTrue()
        ->and($memory->address)->not->toBe(0)
        ->and($ring->memory()->address)->not->toBe($memory->address)
        ->and($frames->paged($spec, 10, 8, 4)->memory())->toBeNull();
})->with([[FormatSpec::rgba8(), 40], [Formats::rgb565(), 20]])->skip(! CLandFramebufferDriver::available(), 'ext-fb is not loaded in this PHP.');

it('rasterizes in C straight into any store ext-fb can blend into, a library\'s included', function (FormatSpec $spec, Edges $edges): void {
    $library = new PartialFramebufferDriver('memory', MemoryPixelStore::class);
    [$direct, $velvet] = [$library->dirty($spec, 40, 30), (new CLandFramebufferDriver)->dirty($spec, 40, 30)];
    $direct->beginEpoch() && $velvet->beginEpoch();
    $raster = (new CLandRasterizationDriver)->rasterizer(new Region(3, 2, 34, 25), $edges);

    MemoryPixelStore::$loads = 0;
    $raster->fillBatch(directShapes(), $direct);
    $raster->fillBatch(directShapes(), $velvet);

    // in place: the library store's pixels were never lent out and loaded back, and they are ext-fb's own store's
    expect(MemoryPixelStore::$loads)->toBe(0)
        ->and(bin2hex($direct->dump()))->toBe(bin2hex($velvet->dump()))
        ->and($direct->damage())->toEqual($velvet->damage());
})->with([FormatSpec::rgba8(), FormatSpec::bgra8(), Formats::rgb565()])->with([Edges::ANTIALIASED, Edges::HARD])->skip($accelerated, NEEDS_EXTENSIONS);

it('gives a scanner that draws in place only its own ops, in runs, in order', function (string $store): void {
    $frames = $store === 'ext' ? new CLandFramebufferDriver : new PartialFramebufferDriver('memory', MemoryPixelStore::class);
    $raster = (new PartialRasterizationDriver('direct-path', DirectPathScanner::class))->rasterizer(new Region(3, 2, 34, 25), Edges::ANTIALIASED);

    CoreOnlyScanner::$asked = [];
    $raster->fillBatch(directShapes(), $frames->full(FormatSpec::rgba8(), 40, 30));

    // the path and the wide polyline in place, five shapes VelvetGE scans, the last path in place
    expect(CoreOnlyScanner::$asked)->toBe(['fill:2', 'fill:1']);
})->with(['ext', 'memory'])->skip($accelerated, NEEDS_EXTENSIONS);

it('lands a mask scanner\'s coverage through the store, on any store, with no span list built', function (FramebufferDriver $frames): void {
    $raster = (new PartialRasterizationDriver('mask-path', MaskPathScanner::class))->rasterizer(new Region(3, 2, 34, 25), Edges::ANTIALIASED);

    CoreOnlyScanner::$asked = [];
    $raster->fillBatch(directShapes(), $frames->full(FormatSpec::rgba8(), 40, 30));

    // one mask an op it has (two paths and the wide polyline), and its path() never asked for spans
    expect(CoreOnlyScanner::$asked)->toBe(['mask', 'mask', 'mask']);
})->with('framebuffer drivers');

it('sends a scanner that cannot draw into the memory through coverage', function (): void {
    $owner = new FbBuffer(new FbFormat(FB_LAYOUT_RGBA8888, channelOrder: FB_CHANNELS_RGBA), 8, 4);
    $scanner = new CLandScanner(new Region(0, 0, 8, 4), Edges::ANTIALIASED);
    $other = new stdClass;    // another library's object owns the bytes

    expect($scanner->drawsInto(new PixelMemory($owner->pointer(), 8, 4, 32, FormatSpec::rgba8(), $owner)))->toBeTrue()
        ->and($scanner->drawsInto(new PixelMemory($owner->pointer(), 8, 4, 32, FormatSpec::rgba8(), $other)))->toBeTrue()
        ->and($scanner->drawsInto(new PixelMemory($owner->pointer(), 8, 4, 36, FormatSpec::rgba8(), $other)))->toBeFalse();   // padded rows
})->skip($accelerated, NEEDS_EXTENSIONS);

it('draws a view in C straight into any 32-bit store, whichever order its bytes are in', function (string $caster, FormatSpec $spec) use ($room, $scenery): void {
    $casters = $caster === 'ext' ? new CLandRaycastingDriver : new LibraryRaycastingDriver;
    $frames = new PartialFramebufferDriver('memory', MemoryPixelStore::class);
    [$painted, $written] = [$frames->dirty($spec, 48, 32), $frames->dirty($spec, 48, 32)];
    $painted->beginEpoch() && $written->beginEpoch();
    $ray = $casters->caster($room());
    $camera = new Camera(2.5, 2.5, 0.7, 1.1);

    MemoryPixelStore::$blits = 0;
    $ray->paint($camera, $scenery(), $painted, new Region(4, 3, 40, 24));
    $blits = MemoryPixelStore::$blits;
    $written->writeRgba8($ray->view($camera, 40, 24, $scenery()), 40, 24, 4, 3);

    // in place: the store was never handed the picture as bytes
    expect($blits)->toBe(0)
        ->and(bin2hex($painted->dump()))->toBe(bin2hex($written->dump()))
        ->and($painted->damage())->toEqual($written->damage());
})->with(['ext', 'a library marcher'])
    ->with(['RGBA' => [FormatSpec::rgba8()], 'BGRA' => [FormatSpec::bgra8()], 'ARGB' => [argb8()]])->skip($accelerated, NEEDS_EXTENSIONS);

it('hands a view to the store as a picture where the marcher cannot draw into its memory', function () use ($room, $scenery): void {
    $painted = (new PartialFramebufferDriver('memory', MemoryPixelStore::class))->full(Formats::rgb565(), 16, 12);

    [MemoryPixelStore::$blits, LibraryMarcher::$asked] = [0, []];
    (new LibraryRaycastingDriver)->caster($room())->paint(new Camera(2.5, 2.5, 0.7, 1.1), $scenery(), $painted);

    expect(MemoryPixelStore::$blits)->toBe(1)
        ->and(LibraryMarcher::$asked)->toBe(['picture']);
})->skip($accelerated, NEEDS_EXTENSIONS);

it('asks a library marcher to draw in place, never for a picture, where it can', function () use ($room, $scenery): void {
    $painted = (new CLandFramebufferDriver)->full(FormatSpec::bgra8(), 16, 12);

    LibraryMarcher::$asked = [];
    (new LibraryRaycastingDriver)->caster($room())->paint(new Camera(2.5, 2.5, 0.7, 1.1), $scenery(), $painted);

    expect(LibraryMarcher::$asked)->toBe(['pictureInto']);
})->skip($accelerated, NEEDS_EXTENSIONS);
