<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\FramebufferDriver;
use Surface\Contracts\Rendering\Framebuffers\FramebufferException;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Venusian\Topside\Tests\Support\Framebuffers\Formats;

/*
 * PixelStore::load() is the inverse of region() in the store's own format: what region() answers
 * for a rect goes back word for word, in every format, on every driver.
 */

it('writes back what region() answered, whole and in part, in every format', function (FramebufferDriver $driver, FormatSpec $spec): void {
    $random = new Random\Randomizer(new Random\Engine\Mt19937(11));
    $source = $driver->full($spec, 13, 9)->writeRgba8($random->getBytes(13 * 9 * 4), 13, 9)->store();
    $target = $driver->full($spec, 13, 9)->store();

    $whole = new Region(0, 0, 13, 9);
    $target->load($whole, $source->region($whole, $spec));
    expect(bin2hex($target->bytes()))->toBe(bin2hex($source->bytes()), 'the whole surface');

    $target->fill(0);
    $part = new Region(3, 2, 7, 5);
    $target->load($part, $source->region($part, $spec));
    expect(bin2hex($target->region($part, $spec)))->toBe(bin2hex($source->region($part, $spec)), 'a rect')
        ->and($target->get(0, 0))->toBe($driver->full($spec, 1, 1)->fill(0)->getPixel(0, 0), 'outside the rect is untouched');
})->with('framebuffer drivers')->with(Formats::dataset());

it('refuses a rect outside the store, and bytes of another length', function (FramebufferDriver $driver): void {
    $store = $driver->full(FormatSpec::rgba8(), 4, 4)->store();

    expect(fn () => $store->load(new Region(2, 2, 4, 4), str_repeat("\0", 64)))->toThrow(FramebufferException::class)
        ->and(fn () => $store->load(new Region(0, 0, 0, 2), ''))->toThrow(FramebufferException::class)
        ->and(fn () => $store->load(new Region(0, 0, 2, 2), str_repeat("\0", 15)))->toThrow(FramebufferException::class, 'takes the 16 bytes of a 2x2 rect');
})->with('framebuffer drivers');
