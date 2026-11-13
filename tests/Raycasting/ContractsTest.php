<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Raycasting\Camera;
use Surface\Contracts\Rendering\Raycasting\Face;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Hit;
use Surface\Contracts\Rendering\Raycasting\Hits;
use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Contracts\Rendering\Raycasting\Scenery;
use Surface\Contracts\Rendering\Raycasting\Texture;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;

it('builds a grid from its rows, top first', function (): void {
    $grid = Grid::of([[1, 0, 255], [0, 2, 0]]);

    expect([$grid->width, $grid->height, $grid->cells])->toBe([3, 2, "\1\0\xFF\0\2\0"])
        ->and($grid->wall(2, 0))->toBe(255)
        ->and($grid->wall(1, 1))->toBe(2)
        ->and($grid->wall(0, 1))->toBe(0);
});

it('answers open for anywhere outside the grid', function (): void {
    $grid = Grid::of([[1]]);

    expect([$grid->wall(-1, 0), $grid->wall(0, -1), $grid->wall(1, 0), $grid->wall(0, 1)])->toBe([0, 0, 0, 0]);
});

it('refuses rows that are not a grid', function (array $rows, string $message): void {
    expect(fn () => Grid::of($rows))->toThrow(RaycastException::class, $message);
})->with([
    [[], 'A grid is a list of rows, at least one'],
    [['a' => [1]], 'A grid is a list of rows, at least one'],
    [[[1, 0], [1]], 'Row 1 of a grid is not a list of 2 cells'],
    [[[1, 0], 'ab'], 'Row 1 of a grid is not a list of 2 cells'],
    [[[]], "A grid's width is 1..65535, got 0"],
    [[[1, 256]], 'Cell (1, 0) of a grid is not a whole number 0..255'],
    [[[1], [-1]], 'Cell (0, 1) of a grid is not a whole number 0..255'],
    [[[1.0]], 'Cell (0, 0) of a grid is not a whole number 0..255'],
]);

it('refuses a grid whose sides or cells do not fit', function (int $width, int $height, string $cells, string $message): void {
    expect(fn () => new Grid($width, $height, $cells))->toThrow(RaycastException::class, $message);
})->with([
    [0, 1, '', "A grid's width is 1..65535, got 0"],
    [1, 65536, '', "A grid's height is 1..65535, got 65536"],
    [2, 2, "\0\0\0", 'A grid 2x2 holds 4 cells, one byte each; got 3 bytes'],
]);

it('looks east at angle 0 and turns toward south', function (): void {
    $east = new Camera(1.5, 2.5, 0.0, 2 * atan(0.5));
    $south = $east->facing(M_PI / 2);

    expect($east->direction())->toBe([1.0, 0.0])
        ->and($east->plane()[1])->toEqualWithDelta(0.5, 1e-15)
        ->and(abs($east->plane()[0]))->toBe(0.0)
        ->and($south->direction()[1])->toBe(1.0)
        ->and($south->direction()[0])->toEqualWithDelta(0.0, 1e-15)
        ->and($south->plane()[0])->toEqualWithDelta(-0.5, 1e-15)
        ->and([$south->x, $south->y, $south->fov])->toBe([1.5, 2.5, $east->fov])
        ->and([$east->at(3.0, 4.0)->x, $east->at(3.0, 4.0)->y, $east->at(3.0, 4.0)->angle])->toBe([3.0, 4.0, 0.0]);
});

it('sees 60 degrees unless told', function (): void {
    expect((new Camera(0.5, 0.5))->fov)->toBe(M_PI / 3);
});

it('refuses a camera that is not finite, too far out, or sees no width or a half turn', function (array $arguments, string $message): void {
    expect(fn () => new Camera(...$arguments))->toThrow(RaycastException::class, $message);
})->with([
    [[NAN, 0.5], "A camera's x is not a finite number"],
    [[0.5, INF], "A camera's y is not a finite number"],
    [[2.0 ** 31, 0.5], "A camera's x 2147483648 is past ±1073741824"],
    [[0.5, 0.5, NAN], "A camera's angle is not a finite number"],
    [[0.5, 0.5, 0.0, 0.0], "A camera's field of view is above 0 and below pi radians"],
    [[0.5, 0.5, 0.0, M_PI], "A camera's field of view is above 0 and below pi radians"],
    [[0.5, 0.5, 0.0, NAN], "A camera's field of view is above 0 and below pi radians"],
]);

it('takes a texture as RGBA8 bytes, or from a framebuffer', function (): void {
    $frame = (new PHPLandFramebufferDriver())->full(FormatSpec::rgba8(), 2, 1)->writeRgba8(hex2bin('ff0000ff00ff00ff'), 2, 1);
    $texture = Texture::of($frame);

    expect([$texture->width, $texture->height, bin2hex($texture->rgba8)])->toBe([2, 1, 'ff0000ff00ff00ff']);
});

it('refuses a texture whose sides or bytes do not fit', function (string $bytes, int $width, int $height, string $message): void {
    expect(fn () => new Texture($bytes, $width, $height))->toThrow(RaycastException::class, $message);
})->with([
    ['', 0, 1, "A texture's width is 1..65535, got 0"],
    ['', 1, 65536, "A texture's height is 1..65535, got 65536"],
    ['abc', 1, 1, 'A texture 1x1 holds 4 bytes of RGBA8; got 3'],
]);

it('gives a driver its walls as plain values', function (): void {
    $scenery = new Scenery([1 => 0xFF0000FF, 7 => new Texture('abcd', 1, 1)], 0x111111FF, 0x222222FF, 170);

    expect($scenery->table())->toBe([1 => 0xFF0000FF, 7 => ['abcd', 1, 1]])
        ->and([$scenery->ceiling, $scenery->floor, $scenery->shade])->toBe([0x111111FF, 0x222222FF, 170])
        ->and((new Scenery([]))->shade)->toBe(255);
});

it('refuses a scenery that is not walls by id, colours and a shade', function (array $arguments, string $message): void {
    expect(fn () => new Scenery(...$arguments))->toThrow(RaycastException::class, $message);
})->with([
    [[[0 => 1]], "A scenery's walls are keyed by wall id, 1..255"],
    [[[256 => 1]], "A scenery's walls are keyed by wall id, 1..255"],
    [[['one' => 1]], "A scenery's walls are keyed by wall id, 1..255"],
    [[[3 => -1]], 'Wall 3 of a scenery is a colour 0xRRGGBBAA or a Texture'],
    [[[3 => 0x100000000]], 'Wall 3 of a scenery is a colour 0xRRGGBBAA or a Texture'],
    [[[3 => 'red']], 'Wall 3 of a scenery is a colour 0xRRGGBBAA or a Texture'],
    [[[3 => ['abcd', 1, 1]]], 'Wall 3 of a scenery is a colour 0xRRGGBBAA or a Texture'],
    [[[], -1], "A scenery's ceiling is a colour 0xRRGGBBAA"],
    [[[], 0, 0x100000000], "A scenery's floor is a colour 0xRRGGBBAA"],
    [[[], 0, 0, 256], "A scenery's shade is 0..255, got 256"],
]);

it('packs a hit into 22 bytes and reads it back; a miss is 22 zero bytes', function (): void {
    $hit = new Hit(1.5, 0.25, 4, 3, 2, Face::NORTH);
    $bytes = Hits::pack($hit).str_repeat("\0", Hits::BYTES);

    expect(strlen(Hits::pack($hit)))->toBe(22)
        ->and(Hits::count($bytes))->toBe(2)
        ->and(Hits::at($bytes, 0))->toEqual($hit)
        ->and(Hits::at($bytes, 1))->toBeNull()
        ->and(Hits::unpack($bytes))->toEqual([$hit, null])
        ->and(Hits::unpack(''))->toBe([]);
});

it('refuses bytes that are not whole records, and a record that is not there', function (): void {
    expect(fn () => Hits::count('abc'))->toThrow(RaycastException::class, 'whole number of 22-byte records, got 3 bytes')
        ->and(fn () => Hits::at(str_repeat("\0", 22), 1))->toThrow(RaycastException::class, 'has no record 1')
        ->and(fn () => Hits::at(str_repeat("\0", 22), -1))->toThrow(RaycastException::class, 'has no record -1');
});

it('shades the north and south faces only', function (): void {
    expect(array_map(fn (Face $face): bool => $face->isShaded(), Face::cases()))->toBe([false, false, true, true]);
});
