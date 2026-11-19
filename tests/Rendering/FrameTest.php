<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Enums\AlphaModel;
use Surface\Contracts\Rendering\Enums\ChannelOrder;
use Surface\Rendering\Frame;
use Surface\Rendering\PixelMemory;
use Surface\Rendering\Region;

/*
 * A frame is where the drawing side says what it wrote and the stage learns what to refresh. A stage that
 * misses a region shows stale pixels; one that gets a region twice refreshes for nothing.
 */

function frame(): Frame
{
    return new Frame(new PixelMemory(1000, 8, 8, 32, ChannelOrder::BGRA, AlphaModel::PREMULTIPLIED, new stdClass()));
}

it('hands out the one memory it was made with', function (): void {
    $memory = new PixelMemory(1000, 8, 8, 32, ChannelOrder::BGRA, AlphaModel::PREMULTIPLIED, new stdClass());
    $frame = new Frame($memory);

    expect($frame->memory())->toBe($memory);
});

it('raises the memory version once for each wrote() that names a region', function (): void {
    $frame = frame();

    $frame->wrote(new Region(0, 0, 2, 2), new Region(4, 4, 2, 2));
    expect($frame->memory()->version)->toBe(1);

    $frame->wrote(new Region(0, 0, 1, 1));
    expect($frame->memory()->version)->toBe(2);
});

it('changes nothing for a wrote() that names no region', function (): void {
    $frame = frame();

    $frame->wrote();

    expect($frame->memory()->version)->toBe(0)
        ->and($frame->takeWritten())->toBe([]);
});

it('gives the written regions once, in the order they were written, then forgets them', function (): void {
    $frame = frame();
    [$first, $second, $third] = [new Region(0, 0, 2, 2), new Region(4, 4, 2, 2), new Region(1, 1, 1, 1)];

    $frame->wrote($first, $second);
    $frame->wrote($third);

    expect($frame->takeWritten())->toBe([$first, $second, $third])
        ->and($frame->takeWritten())->toBe([]);
});

it('keeps the memory version when the regions are taken', function (): void {
    $frame = frame();
    $frame->wrote(new Region(0, 0, 2, 2));

    $frame->takeWritten();

    expect($frame->memory()->version)->toBe(1);
});
