<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Framebuffers\BitDepth;
use Surface\Contracts\Rendering\Framebuffers\ChannelOrder;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\PixelFormat;
use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Painting\Brush;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Rendering\RenderingException;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\GradientSpread;
use Surface\NutsAndBolts\LinearGradient;
use Surface\NutsAndBolts\Path;
use Surface\NutsAndBolts\RadialGradient;
use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Painting\BrushPaintingDriver;
use Surface\VelvetGE\Drivers\Painting\CLandPaintingDriver;
use Surface\VelvetGE\Drivers\Painting\PHPLandPaintingDriver;
use Surface\VelvetGE\Drivers\Rasterization\PHPLandRasterizationDriver;
use Surface\VelvetGE\Painters\CLandBrush;
use Venusian\Topside\Tests\Support\Framebuffers\Formats;

/*
 * A brush paints the commands it takes; VelvetGE's painter paints the rest. Whatever the brush
 * takes, the frame is drawn whole and in order. VelvetGE's own brush in C (ext-paint) scans and
 * blends by VelvetGE's steps, so the 'ext' driver is held to the 'native' one byte for byte.
 */

const BRUSH_W = 120;
const BRUSH_H = 90;

/** A frame that uses every kind of command: a seeded mix of shapes, strokes, gradients, a layer and clears. */
function brushScene(int $seed): Closure
{
    return function (Painter $g) use ($seed): void {
        mt_srand($seed);
        $n = fn (float $low, float $high): float => $low + ($high - $low) * mt_rand() / mt_getrandmax();
        $colour = fn (): Color => Color::rgba(mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 2) ? $n(0.2, 1.0) : 1.0);
        $path = function () use ($n): Path {
            $path = (new Path())->moveTo($n(0, BRUSH_W), $n(0, BRUSH_H));
            for ($i = mt_rand(2, 6); $i > 0; $i--) {
                mt_rand(0, 3) ? $path->lineTo($n(-10, BRUSH_W + 10), $n(-10, BRUSH_H + 10)) : $path->cubicTo($n(0, BRUSH_W), $n(0, BRUSH_H), $n(0, BRUSH_W), $n(0, BRUSH_H), $n(0, BRUSH_W), $n(0, BRUSH_H));
            }

            return $path->close();
        };
        $stops = fn (): array => [[0.0, $colour()], [$n(0.3, 0.7), $colour()], [1.0, $colour()]];
        $spread = fn (): GradientSpread => [GradientSpread::PAD, GradientSpread::REPEAT, GradientSpread::REFLECT][mt_rand(0, 2)];

        $g->clear(Color::rgb(mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        for ($i = 0; $i < 60; $i++) {
            if (mt_rand(0, 9) === 0) {
                $g->clip(mt_rand(0, 1) ? null : new Region(mt_rand(0, 40), mt_rand(0, 30), mt_rand(10, 70), mt_rand(10, 55)));
            }
            match (mt_rand(0, 14)) {
                0 => $g->fillRect($n(-5, 100), $n(-5, 70), $n(1, 60), $n(1, 50), $colour()),
                1 => $g->strokeRect($n(0, 90), $n(0, 60), $n(2, 60), $n(2, 50), $colour(), $n(0.5, 6)),
                2 => $g->line($n(0, BRUSH_W), $n(0, BRUSH_H), $n(0, BRUSH_W), $n(0, BRUSH_H), $colour(), mt_rand(0, 1) ? 1.0 : $n(1.5, 7)),
                3 => $g->fillEllipse($n(0, BRUSH_W), $n(0, BRUSH_H), $n(1, 40), $n(1, 30), $colour()),
                4 => $g->strokeEllipse($n(0, BRUSH_W), $n(0, BRUSH_H), $n(2, 40), $n(2, 30), $colour(), $n(0.5, 8)),
                5 => $g->roundRect($n(0, 80), $n(0, 50), $n(4, 60), $n(4, 50), [$n(0, 16), $n(0, 16), $n(0, 16), $n(0, 16)], $colour(), mt_rand(0, 1) ? $colour() : null, $n(0.5, 5)),
                6 => $g->shadow($n(0, 80), $n(0, 50), $n(4, 60), $n(4, 40), $n(0, 10), $n(0, 9), $colour(), $n(-3, 3), $n(-3, 3)),
                7 => $g->fill($path(), $colour(), mt_rand(0, 1) ? FillRule::NON_ZERO : FillRule::EVEN_ODD),
                8 => $g->stroke($path(), $colour(), new StrokeStyle(width: $n(0.5, 6))),
                9 => $g->fill($path(), new LinearGradient($n(0, BRUSH_W), $n(0, BRUSH_H), $n(0, BRUSH_W), $n(0, BRUSH_H), $stops(), $spread())),
                10 => $g->fill($path(), new RadialGradient($n(0, BRUSH_W), $n(0, BRUSH_H), $n(5, 50), $stops(), $spread())),
                11 => $g->polyline([[$n(0, BRUSH_W), $n(0, BRUSH_H)], [$n(0, BRUSH_W), $n(0, BRUSH_H)], [$n(0, BRUSH_W), $n(0, BRUSH_H)]], $colour(), mt_rand(0, 1) ? 1.0 : $n(1.5, 6), (bool) mt_rand(0, 1)),
                12 => $g->fillTriangle($n(0, BRUSH_W), $n(0, BRUSH_H), $n(0, BRUSH_W), $n(0, BRUSH_H), $n(0, BRUSH_W), $n(0, BRUSH_H), $colour()),
                // commands VelvetGE's brush leaves to the painter, in the middle of ones it takes
                13 => $g->layer($n(0, 60), $n(0, 40), $n(10, 60), $n(10, 50), fn (Painter $g) => $g->fillEllipse(30, 25, 25, 20, $colour())->fillRect(5, 5, 30, 12, $colour()), $n(0.3, 1.0), [6.0, 6.0, 6.0, 6.0]),
                14 => $g->push()->translate($n(-5, 5), $n(-5, 5))->rotate($n(-0.3, 0.3))->fillRect(20, 20, 40, 25, $colour())->pop(),
            };
        }
    };
}

/** @return array{string, list<array{int, int, int, int}>} the pixels as RGBA8 and what the frame damaged */
function brushFrame(BrushPaintingDriver $driver, Framebuffer $target, Edges $edges, int $seed): array
{
    $target->beginEpoch();
    $driver->painter($target, $edges)->frame(brushScene($seed));

    return [
        $target->flushRegion(new Region(0, 0, BRUSH_W, BRUSH_H), FormatSpec::rgba8()),
        array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $target->damage()),
    ];
}

/** A brush that counts what it is given and hands it on. */
function countingBrush(Brush $inner): Brush
{
    return new class($inner) implements Brush {
        public int $runs = 0;

        /** @var array<string, int> */
        public array $kinds = [];

        public function __construct(private readonly Brush $inner) {}

        public function drawsInto(PixelMemory $memory): bool
        {
            return $this->inner->drawsInto($memory);
        }

        public function takes(array $command, Edges $edges): bool
        {
            return $this->inner->takes($command, $edges);
        }

        public function paint(array $commands, Region $rows, PixelMemory $memory, Edges $edges): array
        {
            $this->runs++;
            foreach ($commands as $command) {
                $this->kinds[$command[0]] = ($this->kinds[$command[0]] ?? 0) + 1;
            }

            return $this->inner->paint($commands, $rows, $memory, $edges);
        }
    };
}

/** A driver over VelvetGE's native rasterizer with the brush given. */
function brushDriver(?Brush $brush): BrushPaintingDriver
{
    return new class($brush) extends BrushPaintingDriver {
        public function __construct(private readonly ?Brush $with)
        {
            parent::__construct(new PHPLandRasterizationDriver(), true, new \Surface\VelvetGE\Drivers\Recording\PHPLandRecordingDriver());
        }

        public function driver(): string
        {
            return 'test';
        }

        public function brush(): ?Brush
        {
            return $this->with;
        }
    };
}

function needsPaint(): void
{
    if (! extension_loaded('paint') || ! class_exists(FbBuffer::class)) {
        test()->markTestSkipped('ext-paint and ext-fb are not both loaded in this PHP.');
    }
}

it('paints a frame with its brush in C as the native painter paints it, byte for byte, with the same damage', function (ChannelOrder $order, Edges $edges, int $seed): void {
    needsPaint();
    $spec = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B32, channel_order: $order);
    $native = brushFrame(new PHPLandPaintingDriver(new PHPLandRasterizationDriver(), true, new \Surface\VelvetGE\Drivers\Recording\PHPLandRecordingDriver()), (new CLandFramebufferDriver())->dirty($spec, BRUSH_W, BRUSH_H), $edges, $seed);
    $ext = brushFrame(new CLandPaintingDriver(new PHPLandRasterizationDriver(), true, new \Surface\VelvetGE\Drivers\Recording\PHPLandRecordingDriver()), (new CLandFramebufferDriver())->dirty($spec, BRUSH_W, BRUSH_H), $edges, $seed);

    expect($ext[0] === $native[0])->toBeTrue()
        ->and($ext[1])->toBe($native[1]);
})->with([ChannelOrder::RGBA, ChannelOrder::BGRA, ChannelOrder::ARGB, ChannelOrder::ABGR])->with([Edges::ANTIALIASED, Edges::HARD])->with([1, 2, 3, 4, 5, 6]);

it('gives the brush the commands it takes, in runs, and paints the rest itself in between', function (): void {
    needsPaint();
    $brush = countingBrush(new CLandBrush());
    brushFrame(brushDriver($brush), (new CLandFramebufferDriver())->dirty(FormatSpec::rgba8(), BRUSH_W, BRUSH_H), Edges::ANTIALIASED, 3);

    expect($brush->runs)->toBeGreaterThan(1)
        ->and(array_keys($brush->kinds))->toContain('path', 'ellipse', 'gradpath', 'clear')
        ->and($brush->kinds)->not->toHaveKey('layer');
});

it('paints every command itself where the brush cannot draw into the frame\'s memory', function (Closure $target, bool $inLayers): void {
    needsPaint();
    $brush = countingBrush(new CLandBrush());
    $with = brushFrame(brushDriver($brush), $target(), Edges::ANTIALIASED, 2);
    $without = brushFrame(brushDriver(null), $target(), Edges::ANTIALIASED, 2);

    // a layer is drawn in a 32-bit frame of the target's kind: where that has an address the brush paints the layer's own commands
    expect($brush->runs > 0)->toBe($inLayers)
        ->and($brush->kinds)->not->toHaveKey('clear')
        ->and($with[0] === $without[0])->toBeTrue()
        ->and($with[1])->toBe($without[1]);
})->with([
    'bytes in PHP, with no address' => [fn () => (new PHPLandFramebufferDriver())->dirty(FormatSpec::rgba8(), BRUSH_W, BRUSH_H), false],
    '16-bit pixels' => [fn () => (new CLandFramebufferDriver())->dirty(Formats::rgb565(), BRUSH_W, BRUSH_H), true],
]);

it('draws a whole frame with a brush that takes one kind of command', function (): void {
    needsPaint();
    // only clears: every shape is VelvetGE's, between the brush's runs
    $only = new class(new CLandBrush()) implements Brush {
        public function __construct(private readonly Brush $inner) {}

        public function drawsInto(PixelMemory $memory): bool
        {
            return $this->inner->drawsInto($memory);
        }

        public function takes(array $command, Edges $edges): bool
        {
            return $command[0] === 'clear';
        }

        public function paint(array $commands, Region $rows, PixelMemory $memory, Edges $edges): array
        {
            return $this->inner->paint($commands, $rows, $memory, $edges);
        }
    };
    $target = fn () => (new CLandFramebufferDriver())->dirty(FormatSpec::rgba8(), BRUSH_W, BRUSH_H);
    $scene = fn (Painter $g) => $g->clear(Color::rgb(10, 20, 30))->fillRect(10, 10, 40, 30, Color::rgb(200, 0, 0))->clear(Color::rgb(0, 90, 0), new Region(20, 20, 50, 40))->fillEllipse(60, 45, 30, 20, Color::rgba(255, 255, 255, 0.5));

    $with = $target();
    brushDriver($only)->painter($with)->frame($scene);
    $without = $target();
    brushDriver(null)->painter($without)->frame($scene);
    $whole = new Region(0, 0, BRUSH_W, BRUSH_H);

    expect($with->flushRegion($whole, FormatSpec::rgba8()) === $without->flushRegion($whole, FormatSpec::rgba8()))->toBeTrue();
});

it('is passed the edge mode, and a brush that refuses hard edges is given nothing then', function (): void {
    needsPaint();
    $soft = new class(new CLandBrush()) implements Brush {
        public int $runs = 0;

        public function __construct(private readonly Brush $inner) {}

        public function drawsInto(PixelMemory $memory): bool
        {
            return $this->inner->drawsInto($memory);
        }

        public function takes(array $command, Edges $edges): bool
        {
            return $edges === Edges::ANTIALIASED && $this->inner->takes($command, $edges);
        }

        public function paint(array $commands, Region $rows, PixelMemory $memory, Edges $edges): array
        {
            $this->runs++;

            return $this->inner->paint($commands, $rows, $memory, $edges);
        }
    };
    $target = fn () => (new CLandFramebufferDriver())->dirty(FormatSpec::rgba8(), BRUSH_W, BRUSH_H);

    brushFrame(brushDriver($soft), $target(), Edges::HARD, 1);
    expect($soft->runs)->toBe(0);

    brushFrame(brushDriver($soft), $target(), Edges::ANTIALIASED, 1);
    expect($soft->runs)->toBeGreaterThan(0);
});

it('takes ext when ext-paint is loaded and native otherwise, a library binding first', function (): void {
    $auto = painters()->driver();
    expect($auto->driver())->toBe(extension_loaded('paint') ? 'ext' : 'native')
        ->and(painters()->driver('native')->driver())->toBe('native')
        ->and(painters(['rendering.painter.default' => 'native'])->driver()->driver())->toBe('native');

    $library = painters()->extend('library', fn () => brushDriver(null));
    expect($library->driver()->driver())->toBe('test');

    $refusing = painters()->extend('library', fn () => throw new RenderingException('ext-library is not loaded.'));
    expect($refusing->driver()->driver())->toBe(extension_loaded('paint') ? 'ext' : 'native');
});

it('refuses to make the ext driver without ext-paint', function (): void {
    if (extension_loaded('paint')) {
        $this->markTestSkipped('ext-paint is loaded in this PHP.');
    }

    expect(fn () => new CLandPaintingDriver())->toThrow(RenderingException::class);
});
