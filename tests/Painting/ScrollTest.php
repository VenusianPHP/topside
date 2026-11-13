<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\VelvetGE\Painters\VelvetPainter;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Surface\NutsAndBolts\Color;
use Surface\VelvetGE\Drivers\Rasterization\PHPLandRasterizationDriver;

/*
 * Scrolled drawing: scroll() moves the framebuffer's pixels between frames and the next end()
 * damages the region; a frame that does not clear then draws only the exposed strip.
 */

/** Band $band's colour: each band is 10 rows. */
function scrollBand(int $band): Color
{
    return Color::rgb(30 * $band, 255 - 30 * $band, 40);
}

/** Bands $first.. from row $top down to row $bottom, 10 rows each. */
function scrollBands(VelvetPainter $velvet, int $top, int $bottom, int $first): void
{
    for ($y = $top, $band = $first; $y < $bottom; $y += 10, $band++) {
        $velvet->fillRect(0, $y, 40, 10, scrollBand($band));
    }
}

function scrollCovers(array $damage, Region $region): bool
{
    $all = array_reduce($damage, fn (?Region $all, Region $part): Region => $all?->union($part) ?? $part);

    return ! is_null($all) && $all->intersect($region) == $region;
}

it('scrolls the last frame and damages the region and what the next frame draws', function (): void {
    $buffer = (new PHPLandFramebufferDriver())->full(FormatSpec::rgba8(), 40, 80);
    $velvet = new VelvetPainter($buffer, new PHPLandRasterizationDriver());
    $velvet->begin()->clear(Color::rgb(0, 0, 0));
    scrollBands($velvet, 0, 80, 0);
    $velvet->end();

    expect($velvet->canScroll())->toBeTrue();
    $region = new Region(0, 0, 40, 60);
    $velvet->scroll($region, 0, -20);
    $velvet->begin();
    scrollBands($velvet, 40, 60, 6);
    $velvet->end();

    $reference = (new PHPLandFramebufferDriver())->full(FormatSpec::rgba8(), 40, 80);
    $whole = new VelvetPainter($reference, new PHPLandRasterizationDriver());
    $whole->begin()->clear(Color::rgb(0, 0, 0));
    scrollBands($whole, 0, 60, 2);
    scrollBands($whole, 60, 80, 6);
    $whole->end();

    expect(bin2hex($buffer->toRgba8()))->toBe(bin2hex($reference->toRgba8()))
        ->and(scrollCovers($velvet->damage(), $region))->toBeTrue()
        ->and(scrollCovers($velvet->damage(), new Region(0, 40, 40, 20)))->toBeTrue();
});

it('scrolls a ring\'s last frame, not the older frame its back holds', function (): void {
    $ring = (new PHPLandFramebufferDriver())->ring(FormatSpec::rgba8(), 40, 80, 2);
    $velvet = new VelvetPainter($ring, new PHPLandRasterizationDriver());
    $velvet->begin()->clear(Color::rgb(0, 0, 0));
    scrollBands($velvet, 0, 80, 0);
    $velvet->end();

    expect($velvet->canScroll())->toBeTrue();
    $velvet->scroll(new Region(0, 0, 40, 60), 0, -20);
    $velvet->begin();
    scrollBands($velvet, 40, 60, 6);
    $velvet->end();

    $reference = (new PHPLandFramebufferDriver())->full(FormatSpec::rgba8(), 40, 80);
    $whole = new VelvetPainter($reference, new PHPLandRasterizationDriver());
    $whole->begin()->clear(Color::rgb(0, 0, 0));
    scrollBands($whole, 0, 60, 2);
    scrollBands($whole, 60, 80, 6);
    $whole->end();

    expect(bin2hex($ring->toRgba8()))->toBe(bin2hex($reference->toRgba8()));
});

it('repaints the scrolled region of a frame that clears', function (): void {
    $buffer = (new PHPLandFramebufferDriver())->full(FormatSpec::rgba8(), 40, 80);
    $velvet = new VelvetPainter($buffer, new PHPLandRasterizationDriver());
    $frame = function () use ($velvet): void {
        $velvet->begin()->clear(Color::rgb(0, 0, 0));
        scrollBands($velvet, 0, 80, 0);
        $velvet->end();
    };
    $frame();

    $velvet->scroll(new Region(0, 0, 40, 60), 0, -20);
    $frame();

    $reference = (new PHPLandFramebufferDriver())->full(FormatSpec::rgba8(), 40, 80);
    $whole = new VelvetPainter($reference, new PHPLandRasterizationDriver());
    $whole->begin()->clear(Color::rgb(0, 0, 0));
    scrollBands($whole, 0, 80, 0);
    $whole->end();

    expect(bin2hex($buffer->toRgba8()))->toBe(bin2hex($reference->toRgba8()))
        ->and(scrollCovers($velvet->damage(), new Region(0, 0, 40, 60)))->toBeTrue();
});

it('cannot scroll a framebuffer that does not keep its frame, and changes nothing', function (): void {
    $paged = (new PHPLandFramebufferDriver())->paged(FormatSpec::rgba8(), 40, 40, 10);
    $velvet = new VelvetPainter($paged, new PHPLandRasterizationDriver());
    $velvet->begin()->clear(Color::rgb(9, 9, 9))->end();
    $before = $paged->dump();

    expect($velvet->canScroll())->toBeFalse()
        ->and(fn () => $velvet->scroll(new Region(0, 0, 40, 40), 0, 5))->toThrow(DrawingException::class, 'This framebuffer does not keep its frame: it cannot scroll.')
        ->and($paged->dump())->toBe($before);
});

it('refuses to scroll inside an open frame', function (): void {
    $velvet = new VelvetPainter((new PHPLandFramebufferDriver())->full(FormatSpec::rgba8(), 40, 40), new PHPLandRasterizationDriver());
    $velvet->begin();

    expect(fn () => $velvet->scroll(new Region(0, 0, 40, 40), 0, 5))->toThrow(DrawingException::class, 'A frame is open: scroll() runs between frames.');
});
