<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Rasterization\RasterizeException;
use Surface\Contracts\Rendering\Rasterization\StrokeCap;
use Surface\Contracts\Rendering\Rasterization\StrokeGeometry;
use Surface\Contracts\Rendering\Rasterization\StrokeJoin;
use Surface\Rendering\Rasterization\PHPStroker;
use Surface\Rendering\Rasterization\Stroker;
use Surface\VelvetGE\Rasterize\CLandStroker;

function needsRasterStroker(): void
{
    if (! class_exists(RasterStroker::class, false)) {
        test()->markTestSkipped('ext-rasterize with RasterStroker is not loaded in this PHP.');
    }
}

/** A value with every number as the bits of its float: two answers equal when every bit is. */
function strokeBits(mixed $value): mixed
{
    return is_array($value) ? array_map(strokeBits(...), $value) : (is_float($value) || is_int($value) ? bin2hex(pack('E', (float) $value)) : $value);
}

/** What a stroke call answers, or the class of what it threw. */
function strokeAnswer(Closure $call): array
{
    try {
        return ['answered', strokeBits($call())];
    } catch (Throwable $e) {
        return ['threw', $e::class];
    }
}

/** A polyline for a seed: from a few points to forty, at scales from 1e-9 to 1e6, with repeated points and closed rings now and then. */
function strokePolyline(int $seed): array
{
    mt_srand($seed);
    $scale = [1.0, 0.001, 1.0e-9, 1000.0, 1.0e6][mt_rand(0, 4)];
    $number = fn (): float => $scale * (mt_rand(0, 9) < 2 ? (float) mt_rand(0, 100) : mt_rand(-2000, 12000) / 100);
    $points = [];
    for ($i = mt_rand(0, 40); $i > 0; $i--) {
        $points[] = [$number(), $number()];
        if (mt_rand(0, 9) === 0) {
            $points[] = end($points);
        }
    }
    if (count($points) > 2 && mt_rand(0, 6) === 0) {
        $points[] = $points[0];
    }
    if ($points !== [] && mt_rand(0, 12) === 0) {
        $points = array_fill(0, mt_rand(1, 3), $points[0]);
    }

    return [$points, $scale, (bool) mt_rand(0, 1)];
}

it('strokes a polyline with its C stroker as PHP does: the same bits, the same refusals, over 3,000 polylines', function (): void {
    needsRasterStroker();
    $php = new PHPStroker();
    $c = new CLandStroker();
    $different = [];

    foreach (range(1, 3000) as $seed) {
        [$points, , $closed] = strokePolyline($seed);
        mt_srand($seed + 1);
        $stroke = [0.0, 0.3, 1.0, 2.5, 60.0, 400.0, 1.0e-3, 5.0e-324, 1.0e5, -1.0][mt_rand(0, 9)];
        $cap = StrokeCap::cases()[mt_rand(0, 2)];
        $join = StrokeJoin::cases()[mt_rand(0, 2)];
        $miter = [4.0, 1.0, 1.5, 10.0, 0.0][mt_rand(0, 4)];
        $direction = mt_rand(0, 2) === 0 ? [0.6, 0.8] : null;

        if (strokeAnswer(fn () => $c->outline($points, $stroke, $closed, $cap, $join, $miter, $direction))
            !== strokeAnswer(fn () => $php->outline($points, $stroke, $closed, $cap, $join, $miter, $direction))) {
            $different[] = $seed;
        }
    }

    // the seeds whose answers differed
    expect($different)->toBe([]);
});

it('cuts a polyline into dashes with its C stroker as PHP does: the same bits, the same refusals, over 3,000 polylines', function (): void {
    needsRasterStroker();
    $php = new PHPStroker();
    $c = new CLandStroker();
    $different = [];

    foreach (range(1, 3000) as $seed) {
        [$points, $scale, $closed] = strokePolyline($seed);
        mt_srand($seed + 2);
        $dash = array_map(fn (float $d): float => $d * $scale, [[4.0, 3.0], [5.0], [0.0, 6.0], [2.0, 1.0, 3.0], [10.0, 0.5], [0.3, 0.2], [1.0, 0.0]][mt_rand(0, 6)]);
        $offset = [0.0, 1.5, -2.0, 7.25, 100.0, 3.0][mt_rand(0, 5)] * $scale;

        if (strokeAnswer(fn () => $c->dashed($points, $closed, $dash, $offset)) !== strokeAnswer(fn () => $php->dashed($points, $closed, $dash, $offset))) {
            $different[] = $seed;
        }
    }

    // the seeds whose answers differed
    expect($different)->toBe([]);
});

it('strokes nothing for a stroke of 0 or less, in PHP as in C', function (float $stroke): void {
    $corner = [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0]];

    expect((new PHPStroker())->outline($corner, $stroke, false))->toBe([]);
    if (class_exists(RasterStroker::class, false)) {
        expect((new CLandStroker())->outline($corner, $stroke, false))->toBe([]);
    }
})->with([0.0, -1.0, -1.0e9, NAN]);

it('cuts nothing for a pattern with nothing in it longer than 1e-12, in PHP as in C', function (): void {
    $line = [[0.0, 0.0], [10.0, 0.0]];

    expect((new PHPStroker())->dashed($line, false, [1.0e-13, 1.0e-13], 0.0))->toBe([]);
    if (class_exists(RasterStroker::class, false)) {
        expect((new CLandStroker())->dashed($line, false, [1.0e-13, 1.0e-13], 0.0))->toBe([]);
    }
});

it('strokes with PHP\'s unless something else is brought, and puts PHP\'s back when asked', function (): void {
    $before = Stroker::geometry();
    $counting = new class implements StrokeGeometry {
        public int $calls = 0;

        public function outline(array $points, float $stroke, bool $closed, StrokeCap $cap = StrokeCap::BUTT, StrokeJoin $join = StrokeJoin::MITER, float $miterLimit = self::MITER_LIMIT, ?array $direction = null): array
        {
            $this->calls++;

            return [[1.0, 2.0, 3.0]];
        }

        public function dashed(array $points, bool $closed, array $dash, float $offset): array
        {
            $this->calls++;

            return [];
        }
    };

    try {
        Stroker::use($counting);
        expect(Stroker::outline([[0.0, 0.0], [4.0, 0.0]], 2.0, false))->toBe([[1.0, 2.0, 3.0]])
            ->and(Stroker::dashed([[0.0, 0.0], [4.0, 0.0]], false, [1.0], 0.0))->toBe([])
            ->and($counting->calls)->toBe(2);

        Stroker::use(null);
        expect(Stroker::geometry())->toBeInstanceOf(PHPStroker::class)
            ->and(Stroker::outline([[0.0, 0.0], [6.0, 0.0]], 2.0, false))->toBe([[0.0, -1.0, 6.0, -1.0, 6.0, 1.0, 0.0, 1.0]]);
    } finally {
        Stroker::use($before);
    }
});

it('refuses to make the C stroker without ext-rasterize', function (): void {
    if (class_exists(RasterStroker::class, false)) {
        $this->markTestSkipped('ext-rasterize with RasterStroker is loaded in this PHP.');
    }

    expect(fn () => new CLandStroker())->toThrow(RasterizeException::class);
});
