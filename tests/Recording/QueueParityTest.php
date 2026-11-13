<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\Effects\Blur;
use Surface\Contracts\Graphics\Effects\BlurSampling;
use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Graphics\Effects\Glass;
use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Rendering\Recording\Recorder;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\LinearGradient;
use Surface\NutsAndBolts\Path;
use Surface\VelvetGE\Recording\CLandQueue;
use Surface\VelvetGE\Recording\PHPLandQueue;
use Surface\VelvetGE\Recording\VelvetRecorder;

const QUEUE_W = 160;
const QUEUE_H = 120;

function needsPixelQueue(): void
{
    if (! extension_loaded('pixel_queue')) {
        test()->markTestSkipped('ext-pixel-queue is not loaded in this PHP.');
    }
}

/** A number a caller might give: mostly small, sometimes on a pixel edge, sometimes one a recorder refuses. */
function queueNumber(float $span, bool $wild): float
{
    $roll = mt_rand(0, 999);
    if ($wild && $roll < 12) {
        return [NAN, INF, -INF, 1.0e12, -1.0e12, 3.0e7][mt_rand(0, 5)];
    }
    if ($roll < 200) {
        return (float) mt_rand((int) -$span / 4, (int) $span);
    }

    return mt_rand((int) (-$span * 250), (int) ($span * 1000)) / 1000;
}

/** A size: sometimes zero or negative, which draws nothing. */
function queueSize(float $span, bool $wild): float
{
    $roll = mt_rand(0, 99);
    if ($roll < 4) {
        return 0.0;
    }
    if ($roll < 8) {
        return -1.5;
    }

    return abs(queueNumber($span, $wild)) + 0.25;
}

/**
 * One frame of every kind of call, from a seed. A call a recorder refuses is caught and its
 * message put in $log, so two recorders are held to refusing the same calls in the same words.
 *
 * @param  list<string>  $log
 */
function queueScene(Painter $g, int $seed, Framebuffer $image, array &$log, bool $wild, int $calls = 60, bool $clears = true): void
{
    mt_srand($seed);
    $colour = fn (): Color => Color::rgba(mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 100) / 100);
    $n = fn (float $span = 120.0): float => queueNumber($span, $wild);
    $s = fn (float $span = 60.0): float => queueSize($span, $wild);
    $points = fn (int $count): array => array_map(fn (): array => [$n(), $n()], range(1, $count));
    $radii = fn (): array => match (mt_rand(0, 12)) {
        0 => [1, 2, 3],
        1 => [1, 'two', 3, 4],
        2 => $wild ? [NAN, 1, 1, 1] : [2, 2, 2, 2],
        default => [mt_rand(0, 20), mt_rand(0, 200) / 10, mt_rand(0, 12), mt_rand(0, 300) / 10],
    };
    $pushed = 0;
    if ($clears) {
        $g->clear($colour());
    }

    $shapes = function (Painter $g, int $count, int $depth) use (&$shapes, &$pushed, &$log, $colour, $n, $s, $points, $radii, $image, $wild): void {
        for ($i = 0; $i < $count; $i++) {
            try {
                match (mt_rand(0, 27)) {
                    0 => $g->translate($n(40), $n(40)),
                    1 => $g->scale(mt_rand(5, 30) / 10, mt_rand(0, 3) ? null : mt_rand(5, 30) / 10),
                    2 => $g->rotate(mt_rand(-314, 314) / 100),
                    3 => $g->transform(new Affine(1.0, mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10, 1.0, $n(20), $n(20))),
                    4 => [$g->push(), $pushed++],
                    5 => $pushed > 0 || mt_rand(0, 9) === 0 ? [$g->pop(), $pushed--] : null,
                    6 => $g->clip(mt_rand(0, 3) ? new Region(mt_rand(-20, 100), mt_rand(-20, 80), mt_rand(0, 120), mt_rand(0, 100)) : null),
                    7, 8, 9 => $g->fillRect($n(), $n(), $s(), $s(), $colour()),
                    10 => $g->strokeRect($n(), $n(), $s(), $s(), $colour(), $s(6)),
                    11 => $g->line($n(), $n(), $n(), $n(), $colour(), $s(6)),
                    12 => $g->polyline($points(mt_rand(0, 6)), $colour(), $s(5), (bool) mt_rand(0, 1)),
                    13 => $g->fillTriangle($n(), $n(), $n(), $n(), $n(), $n(), $colour()),
                    14 => $g->fillPolygon($points(mt_rand(1, 7)), $colour(), mt_rand(0, 1) ? FillRule::NON_ZERO : FillRule::EVEN_ODD),
                    15 => $g->fillPath([$points(mt_rand(2, 5)), $points(mt_rand(3, 5))], $colour(), FillRule::EVEN_ODD),
                    16 => $g->fill((new Path())->moveTo($n(), $n())->lineTo($n(), $n())->quadTo($n(), $n(), $n(), $n())->close(), mt_rand(0, 1) ? $colour() : new LinearGradient(0, 0, 80, 60, [[0.0, Color::rgb(255, 0, 0)], [1.0, Color::rgba(0, 0, 255, 0.5)]])),
                    17 => $g->stroke((new Path())->moveTo($n(), $n())->lineTo($n(), $n())->lineTo($n(), $n()), $colour(), new StrokeStyle(width: $s(5), dash: mt_rand(0, 1) ? [3.0, 2.0] : [])),
                    18, 19 => $g->fillEllipse($n(), $n(), $s(30), $s(30), $colour()),
                    20 => $g->strokeEllipse($n(), $n(), $s(30), $s(30), $colour(), $s(5)),
                    21, 22 => $g->roundRect($n(), $n(), $s(), $s(), $radii(), $colour(), mt_rand(0, 1) ? $colour() : null, mt_rand(-2, 8) / 2),
                    23 => $g->shadow($n(), $n(), $s(), $s(), $s(10), mt_rand(-2, 12) / 1, $colour(), $n(6), $n(6)),
                    24 => $g->image($image, $n(), $n(), mt_rand(0, 1) ? $s() : null, mt_rand(0, 1) ? $s() : null, mt_rand(0, 100) / 100),
                    25 => $g->glass($n(), $n(), $s(), $s(), mt_rand(0, 1) ? [4, 4, 4, 4] : null, new Glass(blur: mt_rand(0, 12) / 1, shadow: mt_rand(0, 1) * 6.0, shadowColor: mt_rand(0, 1) ? Color::rgba(0, 0, 0, 0.4) : null), mt_rand(20, 100) / 100),
                    26, 27 => $depth > 1 ? null : $g->layer($n(), $n(), $s(), $s(), function (Painter $g) use (&$shapes, &$pushed, $depth): void {
                        $outside = $pushed;
                        $pushed = 0;
                        try {
                            $shapes($g, mt_rand(0, 6), $depth + 1);
                            // sometimes a push is left behind, which the layer refuses
                            if (mt_rand(0, 14) !== 0) {
                                while ($pushed > 0) {
                                    $g->pop();
                                    $pushed--;
                                }
                            }
                        } finally {
                            $pushed = $outside;
                        }
                    }, mt_rand(0, 100) / 100, mt_rand(0, 1) ? [3, 3, 3, 3] : null, null, mt_rand(0, 2) ? null : new FilterChain([new Blur(BlurSampling::GAUSSIAN, 3.0, 3.0)])),
                };
            } catch (Throwable $e) {
                $log[] = $e::class.': '.$e->getMessage();
            }
        }
    };
    $shapes($g, $calls, 0);
}

/** A value with every float as its bits, and every object as its class and what it holds, so two command lists compare exactly. */
function queueExact(mixed $value): mixed
{
    return match (true) {
        is_float($value) => 'f:'.bin2hex(pack('E', $value)),
        is_array($value) => array_map(queueExact(...), $value),
        $value instanceof Region => ['Region', $value->x, $value->y, $value->width, $value->height],
        $value instanceof Affine => ['Affine', ...array_map(queueExact(...), $value->toArray())],
        $value instanceof UnitEnum => $value::class.'::'.$value->name,
        $value instanceof Framebuffer => $value::class.'#'.spl_object_id($value),
        is_object($value) => [$value::class, queueExact(get_object_vars($value))],
        default => $value,
    };
}

function queueRegions(array $regions): array
{
    return array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $regions);
}

/** @return array{Recorder, Recorder} VelvetGE's recorder over its queue in PHP, and over its queue in C */
function queueRecorders(): array
{
    return [new VelvetRecorder(new PHPLandQueue()), new VelvetRecorder(new CLandQueue())];
}

it('records every draw call with its queue in C as its queue in PHP does: the same commands, bit for bit, and the same refusals', function (int $seed, bool $wild): void {
    needsPixelQueue();
    $image = nativeFull(FormatSpec::rgba8(), 24, 16);
    [$native, $ext] = queueRecorders();
    $logs = [[], []];
    $frames = [];
    foreach ([$native, $ext] as $which => $recorder) {
        $engine = new RecordingEngine(QUEUE_W, QUEUE_H, $recorder);
        $frames[$which] = queueExact($engine->commandsOf(function (Painter $g) use ($seed, $image, &$logs, $which, $wild): void {
            queueScene($g, $seed, $image, $logs[$which], $wild);
        }));
    }

    expect($frames[1])->toBe($frames[0])
        ->and($logs[1])->toBe($logs[0]);
})->with(range(1, 60))->with([false, true]);

it('refuses a draw call outside a frame in the words of the queue in PHP', function (): void {
    needsPixelQueue();
    $calls = [
        fn (Recorder $r) => $r->clear(Color::rgb(0, 0, 0)),
        fn (Recorder $r) => $r->fillRect(0, 0, 1, 1, Color::rgb(0, 0, 0)),
        fn (Recorder $r) => $r->strokeRect(0, 0, 1, 1, Color::rgb(0, 0, 0)),
        fn (Recorder $r) => $r->line(0, 0, 1, 1, Color::rgb(0, 0, 0)),
        fn (Recorder $r) => $r->fillTriangle(0, 0, 1, 1, 2, 0, Color::rgb(0, 0, 0)),
        fn (Recorder $r) => $r->fillEllipse(0, 0, 1, 1, Color::rgb(0, 0, 0)),
        fn (Recorder $r) => $r->strokeEllipse(0, 0, 1, 1, Color::rgb(0, 0, 0)),
        fn (Recorder $r) => $r->roundRect(0, 0, 1, 1, [0, 0, 0, 0], Color::rgb(0, 0, 0)),
        fn (Recorder $r) => $r->shadow(0, 0, 1, 1, 0, 1, Color::rgb(0, 0, 0)),
        fn (Recorder $r) => $r->push(),
        fn (Recorder $r) => $r->pop(),
        fn (Recorder $r) => $r->translate(1, 1),
        fn (Recorder $r) => $r->clip(null),
    ];
    [$native, $ext] = queueRecorders();
    foreach ($calls as $call) {
        $said = [];
        foreach ([$native, $ext] as $recorder) {
            try {
                $call($recorder);
                $said[] = 'nothing';
            } catch (Throwable $e) {
                $said[] = $e::class.': '.$e->getMessage();
            }
        }
        expect($said[1])->toBe($said[0])->and($said[0])->toContain('belongs inside a frame');
    }
});

it('answers the same regions with its queue in C as with its queue in PHP, for what a frame draws and for what changed', function (int $seed, DamageGranularity $granularity): void {
    needsPixelQueue();
    $image = nativeFull(FormatSpec::rgba8(), 24, 16);
    $surface = new Region(0, 0, QUEUE_W, QUEUE_H);
    [$native, $ext] = queueRecorders();
    $engines = [new RecordingEngine(QUEUE_W, QUEUE_H, $native), new RecordingEngine(QUEUE_W, QUEUE_H, $ext)];
    $also = [[], [new Region(10, 20, 30, 5)], [new Region(0, 0, 4, 4), new Region(150, 110, 10, 10)]];

    // six frames, each a little off the one before: the same seed gives the same start, and a different count of calls a different tail
    foreach ([40, 40, 41, 37, 40, 12] as $step => $calls) {
        $answers = [];
        foreach ([$native, $ext] as $which => $recorder) {
            $log = [];
            $engines[$which]->frame(function (Painter $g) use ($seed, $step, $image, &$log, $calls): void {
                queueScene($g, $seed + intdiv($step, 3), $image, $log, false, $calls, $step !== 4);
            });
            $answers[$which] = [
                queueRegions($recorder->drawn($surface, $granularity, $also[$step % 3])),
                queueRegions($recorder->changed($surface, $granularity, $also[$step % 3])),
                queueRegions($engines[$which]->damage()),
            ];
        }
        expect($answers[1])->toBe($answers[0]);
    }
})->with(range(1, 40))->with([
    'pixels' => [DamageGranularity::pixel(QUEUE_W, QUEUE_H)],
    'rows of 8' => [DamageGranularity::rows(8, QUEUE_W, QUEUE_H)],
    'blocks of 16 by 4' => [new DamageGranularity(16, 4, QUEUE_W, QUEUE_H)],
    'the whole surface' => [DamageGranularity::wholeSurface(QUEUE_W, QUEUE_H)],
]);

it('holds a frame it was told to drop to nothing, and keeps comparing with the last one finished', function (): void {
    needsPixelQueue();
    $surface = new Region(0, 0, 40, 30);
    $grain = DamageGranularity::pixel(40, 30);
    $answers = [];
    foreach (queueRecorders() as $recorder) {
        $red = Color::rgb(255, 0, 0);
        $recorder->begin($surface);
        $recorder->clear(Color::rgb(0, 0, 0));
        $recorder->fillRect(2, 2, 5, 5, $red);
        $recorder->finish();

        $recorder->begin($surface);
        $recorder->fillRect(20, 20, 5, 5, $red);
        $recorder->abandon();

        $recorder->begin($surface);
        $recorder->clear(Color::rgb(0, 0, 0));
        $recorder->fillRect(3, 2, 5, 5, $red);
        $recorder->finish();
        $answers[] = [$recorder->drawing(), queueRegions($recorder->changed($surface, $grain))];
    }

    expect($answers[1])->toBe($answers[0])->and($answers[0])->toBe([false, [[1, 1, 8, 7]]]);
});
