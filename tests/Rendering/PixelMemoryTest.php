<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Enums\AlphaModel;
use Surface\Contracts\Rendering\Enums\ChannelOrder;
use Surface\Contracts\Rendering\PixelMemoryException;
use Surface\Rendering\PixelMemory;

/*
 * PixelMemory describes native memory to the libraries that draw into it and the stage that shows it. It never
 * reads the memory, so these tests give it an address that is only a number.
 */

function pixelMemory(int $address = 1000, int $width = 3, int $height = 2, int $stride = 16): PixelMemory
{
    return new PixelMemory($address, $width, $height, $stride, ChannelOrder::BGRA, AlphaModel::PREMULTIPLIED, new stdClass());
}

it('refuses address 0', function (): void {
    expect(fn () => pixelMemory(address: 0))->toThrow(PixelMemoryException::class);
});

it('refuses a size with no pixel in it', function (int $width, int $height): void {
    expect(fn () => pixelMemory(width: $width, height: $height, stride: 64))->toThrow(PixelMemoryException::class, "got {$width}x{$height}");
})->with([[0, 2], [3, 0], [-3, 2]]);

it('takes a memory one pixel wide, and one pixel tall', function (int $width, int $height): void {
    $memory = pixelMemory(width: $width, height: $height, stride: 64);

    expect([$memory->width, $memory->height])->toBe([$width, $height]);
})->with([[1, 2], [3, 1], [1, 1]]);

it('refuses a stride shorter than a row of 4-byte pixels', function (): void {
    expect(fn () => pixelMemory(width: 3, stride: 11))->toThrow(PixelMemoryException::class, 'the stride is 11');
});

it('takes a stride of exactly one row, and one with padding', function (int $stride): void {
    expect(pixelMemory(width: 3, stride: $stride)->stride)->toBe($stride);
})->with([12, 16]);

it('finds a pixel by rows of the stride and columns of 4 bytes', function (): void {
    // address 1000, stride 16: pixel (2, 1) is 1000 + 1 * 16 + 2 * 4
    expect(pixelMemory()->at(2, 1))->toBe(1024)
        ->and(pixelMemory()->at(0, 0))->toBe(1000);
});

it('has the whole memory as its bounds', function (): void {
    $bounds = pixelMemory(width: 3, height: 2)->bounds();

    expect([$bounds->x, $bounds->y, $bounds->width, $bounds->height])->toBe([0, 0, 3, 2]);
});

it('counts each wrote() in its version, and lets nothing else change it', function (): void {
    $memory = pixelMemory();
    expect($memory->version)->toBe(0);

    $memory->wrote();
    $memory->wrote();
    expect($memory->version)->toBe(2);

    expect(fn () => $memory->version = 9)->toThrow(Error::class);
});

it('keeps the allocation it describes: a field cannot be written', function (string $field): void {
    $memory = pixelMemory();

    expect(fn () => $memory->{$field} = 1)->toThrow(Error::class);
})->with(['address', 'width', 'height', 'stride']);
