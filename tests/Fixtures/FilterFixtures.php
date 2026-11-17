<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\Effects\Blend;
use Surface\Contracts\Graphics\Effects\BlendMode;
use Surface\Contracts\Graphics\Effects\Blur;
use Surface\Contracts\Graphics\Effects\ColorMatrix;
use Surface\Contracts\Graphics\Effects\Composite;
use Surface\Contracts\Graphics\Effects\CompositeOperator;
use Surface\Contracts\Graphics\Effects\Displace;
use Surface\Contracts\Graphics\Effects\EdgeMode;
use Surface\Contracts\Graphics\Effects\Field;
use Surface\Contracts\Graphics\Effects\Flood;
use Surface\Contracts\Graphics\Effects\Noise;
use Surface\Contracts\Graphics\Effects\Table;
use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Rendering\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\NutsAndBolts\Color;
use Venusian\Topside\Tests\Support\Framebuffers\Rgba8Source;

function filterImage(int $w, int $h): string
{
    $bytes = '';
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $bytes .= chr(($x * 37 + $y * 11) % 256).chr(($x * 5 + $y * 71 + 40) % 256).chr(($x * 13 + $y * 29 + 90) % 256).chr(($x + $y) % 3 === 0 ? 128 : 255);
        }
    }

    return $bytes;
}

function filterMap(int $w, int $h): InstanceThatManagesAFramebuffer
{
    $bytes = '';
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $bytes .= chr(($x * 23 + 60) % 256).chr(($y * 31 + 90) % 256).chr(128)."\xff";
        }
    }

    return new Rgba8Source($bytes, $w, $h);
}


function filterChains(): array
{
    return [
        'gaussian below 2' => FilterChain::of(Blur::gaussian(1.2, 0.6, EdgeMode::DUPLICATE)),
        'gaussian, odd boxes' => FilterChain::of(Blur::gaussian(2.5, 2.5)),
        'gaussian, even boxes, one axis' => FilterChain::of(Blur::gaussian(2.2, 0.0, EdgeMode::DUPLICATE)),
        'spiral' => FilterChain::of(Blur::spiral(3.5, 16)),
        'spiral, edges held' => FilterChain::of(Blur::spiral(5.0, 7, EdgeMode::DUPLICATE)),
        'colour matrix' => FilterChain::of(ColorMatrix::saturate(0.3), ColorMatrix::hueRotate(40.0), ColorMatrix::brightness(0.8, 0.1)),
        'luminance to alpha' => FilterChain::of(ColorMatrix::luminanceToAlpha()),
        'flood and composite' => FilterChain::of(new Flood(Color::rgba(200, 40, 90, 0.6), 'f'), new Composite('source', CompositeOperator::ATOP, in: 'f')),
        'every composite' => FilterChain::of(new Flood(Color::rgba(10, 200, 90, 0.5), 'f'), new Composite('f', CompositeOperator::OVER, result: 'a'), new Composite('f', CompositeOperator::IN, in: 'source', result: 'b'), new Composite('f', CompositeOperator::OUT, in: 'source', result: 'c'), new Composite('f', CompositeOperator::XOR, in: 'source', result: 'd'), Composite::arithmetic('c', 0.3, 0.5, 0.4, 0.05, in: 'd')),
        'every blend' => FilterChain::of(new Flood(Color::rgba(90, 120, 200, 0.7), 'f'), new Blend('f', BlendMode::MULTIPLY, result: 'm'), new Blend('f', BlendMode::SCREEN, in: 'm', result: 's'), new Blend('f', BlendMode::DARKEN, in: 's', result: 'd'), new Blend('f', BlendMode::LIGHTEN, in: 'd')),
        'turbulence' => FilterChain::of(Noise::turbulence(0.07, 0.11, 3, 5)),
        'fractal, stitched' => FilterChain::of(Noise::fractal(0.05, 0.05, 2, 12, true)),
        'hash noise' => FilterChain::of(Noise::hash(9)),
        'displacement' => FilterChain::of(new Displace(filterMap(24, 16), 6.5)),
        'dispersion' => FilterChain::of(new Displace(filterMap(24, 16), 9.0, bands: 7, spread: 0.35)),
        'field of a rounded box' => FilterChain::of(Field::roundedBox([3.2, 2.7, 20.4, 13.1], [5.0, 3.0, 6.5, 1.0], -4.0, 0.0)),
        'field inside a box' => FilterChain::of(Field::inside([3.0, 2.0, 21.0, 14.0], [4.0, 4.0, 4.0, 4.0])),
        'field ramp' => FilterChain::of(Field::ramp(2.0, 14.0)),
        'table on a field' => FilterChain::of(Field::roundedBox([3.2, 2.7, 20.4, 13.1], [5.0, 3.0, 6.5, 1.0], -4.0, 0.0), Table::rgb(fn (float $v): float => $v ** 3)),
        'table on the source, with an alpha table' => FilterChain::of(new Table(array_map(fn ($i) => 255 - $i, range(0, 255)), null, array_map(fn ($i) => intdiv($i, 2), range(0, 255)), array_map(fn ($i) => max($i, 40), range(0, 255)))),
        'displacement by a map a field makes' => FilterChain::of(Field::roundedBox([2.0, 2.0, 22.0, 14.0], [4.0, 4.0, 4.0, 4.0], -6.0, 0.0, 'm'), new Displace('m', 7.0, in: 'source', bands: 3, spread: 0.2)),
        'blur, noise and displacement named' => FilterChain::of(Blur::gaussian(3.0, 3.0, result: 'b'), Noise::turbulence(0.1, 0.1, 2, 3, result: 'n'), new Displace(filterMap(24, 16), 5.0, in: 'b'), new Composite('n', CompositeOperator::ARITHMETIC, 0.0, 0.8, 0.2, 0.0)),
    ];
}

