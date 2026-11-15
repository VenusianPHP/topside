<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\RasterizeDriver;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\GradientSpread;
use Surface\NutsAndBolts\LinearGradient;
use Surface\NutsAndBolts\Path;
use Surface\NutsAndBolts\RadialGradient;
use VelvetGE\Painting\VelvetPainter;

/*
 * A gradient is baked into a ramp of 256 colours; a point picks the entry floor(t × 255 + ½) of its position t along
 * the gradient, spread applied. Velvet paints it through the path's coverage.
 */

function blackToWhite(float $x0 = 0, float $x1 = 255, GradientSpread $spread = GradientSpread::PAD): LinearGradient
{
    return new LinearGradient($x0, 0, $x1, 0, [[0.0, Color::rgb(0, 0, 0)], [1.0, Color::rgb(255, 255, 255)]], $spread);
}

it('bakes the stops into a ramp of 256 colours, interpolated per channel and rounded to a byte', function (): void {
    $ramp = blackToWhite()->ramp();

    expect(strlen($ramp))->toBe(1024)
        ->and(bin2hex(substr($ramp, 0, 4)))->toBe('000000ff')
        ->and(bin2hex(substr($ramp, 100 * 4, 4)))->toBe('646464ff')
        ->and(bin2hex(substr($ramp, 255 * 4, 4)))->toBe('ffffffff');
});

it('holds the end colours outside the first and last stop, and takes the later stop at equal offsets', function (): void {
    $red = Color::rgb(255, 0, 0);
    $blue = Color::rgb(0, 0, 255);
    $ramp = (new LinearGradient(0, 0, 1, 0, [[0.25, $red], [0.5, $red], [0.5, $blue], [0.75, $blue]]))->ramp();
    $at = fn (int $i): string => bin2hex(substr($ramp, $i * 4, 4));

    expect($at(0))->toBe('ff0000ff')
        ->and($at(127))->toBe('ff0000ff')
        ->and($at(128))->toBe('0000ffff')
        ->and($at(255))->toBe('0000ffff');
});

it('interpolates alpha like the colours', function (): void {
    $ramp = (new LinearGradient(0, 0, 1, 0, [[0.0, Color::rgba(255, 0, 0, 0.0)], [1.0, Color::rgba(255, 0, 0, 1.0)]]))->ramp();

    expect(bin2hex(substr($ramp, 0, 4)))->toBe('ff000000')
        ->and(bin2hex(substr($ramp, 255 * 4, 4)))->toBe('ff0000ff');
});

it('turns a position into a ramp entry, spread past the ends', function (GradientSpread $spread, float $t, int $entry): void {
    expect(Gradient::index(Gradient::at('linear', $spread, $t, 0.0)))->toBe($entry);
})->with([
    'pad before' => [GradientSpread::PAD, -0.5, 0],
    'pad after' => [GradientSpread::PAD, 1.5, 255],
    'pad inside' => [GradientSpread::PAD, 0.5, 128],
    'repeat' => [GradientSpread::REPEAT, 1.25, 64],
    'repeat before' => [GradientSpread::REPEAT, -0.25, 191],
    'reflect' => [GradientSpread::REFLECT, 1.25, 191],
    'reflect again' => [GradientSpread::REFLECT, 2.25, 64],
]);

it('measures a radial gradient from its centre', function (): void {
    expect(Gradient::at('radial', GradientSpread::PAD, 0.3, 0.4))->toEqualWithDelta(0.5, 1e-12);
});

it('maps the surface into a linear gradient so that its start is 0 and its end is 1', function (): void {
    $gradient = blackToWhite(10, 30);
    $inverse = $gradient->toGradientSpace(Affine::translation(100, 0));
    [$u0] = $inverse->apply(110.0, 5.0);
    [$u1] = $inverse->apply(130.0, 5.0);

    expect($u0)->toEqualWithDelta(0.0, 1e-9)
        ->and($u1)->toEqualWithDelta(1.0, 1e-9);
});

it('keeps a gradient the same under a uniform scale of the transform and the path alike', function (): void {
    $radial = new RadialGradient(10, 10, 5, [[0.0, Color::rgb(0, 0, 0)], [1.0, Color::rgb(255, 255, 255)]]);
    $inverse = $radial->toGradientSpace(Affine::scaling(2, 2));
    [$u, $v] = $inverse->apply(20.0 + 10.0, 20.0);

    expect(hypot($u, $v))->toEqualWithDelta(1.0, 1e-9);
});

it('refuses a gradient that is not one', function (Closure $make): void {
    expect($make)->toThrow(InvalidArgumentException::class);
})->with([
    'no stops' => [fn () => new LinearGradient(0, 0, 1, 0, [])],
    'an offset past 1' => [fn () => new LinearGradient(0, 0, 1, 0, [[0.0, Color::rgb(0, 0, 0)], [1.5, Color::rgb(0, 0, 0)]])],
    'falling offsets' => [fn () => new LinearGradient(0, 0, 1, 0, [[0.5, Color::rgb(0, 0, 0)], [0.25, Color::rgb(0, 0, 0)]])],
    'a stop that is not a pair' => [fn () => new LinearGradient(0, 0, 1, 0, [[0.5]])],
    'one point' => [fn () => new LinearGradient(3, 3, 3, 3, [[0.0, Color::rgb(0, 0, 0)]])],
    'a radius of 0' => [fn () => new RadialGradient(0, 0, 0, [[0.0, Color::rgb(0, 0, 0)]])],
    'a number that is not finite' => [fn () => new RadialGradient(NAN, 0, 5, [[0.0, Color::rgb(0, 0, 0)]])],
]);
