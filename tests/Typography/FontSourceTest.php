<?php

declare(strict_types=1);

use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\TypographyException;

/* A font source: a file, a face in it, and for a variable font the instance as its coordinates. */

it('keys a face by file and index, and an instance by its coordinates sorted by axis tag', function (): void {
    $default = new FontSource('/fonts/SF-Pro.ttf');
    $instance = new FontSource('/fonts/SF-Pro.ttf', 0, ['wght' => 200, 'wdth' => 100, 'opsz' => 12.5]);
    $sameInstance = new FontSource('/fonts/SF-Pro.ttf', 0, ['opsz' => 12.5, 'wght' => 200.0, 'wdth' => 100]);

    expect($default->key())->toBe('/fonts/SF-Pro.ttf#0')
        ->and($default->variations)->toBe([])
        ->and($instance->variations)->toBe(['opsz' => 12.5, 'wdth' => 100.0, 'wght' => 200.0])
        ->and($instance->key())->toBe('/fonts/SF-Pro.ttf#0@opsz=12.5,wdth=100,wght=200')
        ->and($sameInstance->key())->toBe($instance->key())
        ->and((new FontSource('/fonts/Pair.ttc', 1))->key())->toBe('/fonts/Pair.ttc#1');
});

it('refuses an axis tag that is not four printable characters, and a value that is not finite', function (): void {
    expect(fn () => new FontSource('/f.ttf', 0, ['wgh' => 200]))->toThrow(TypographyException::class, "got 'wgh'")
        ->and(fn () => new FontSource('/f.ttf', 0, ['wghtx' => 200]))->toThrow(TypographyException::class, "got 'wghtx'")
        ->and(fn () => new FontSource('/f.ttf', 0, ["wg\nt" => 200]))->toThrow(TypographyException::class, 'four-character')
        ->and(fn () => new FontSource('/f.ttf', 0, [7 => 200]))->toThrow(TypographyException::class, "got '7'")
        ->and(fn () => new FontSource('/f.ttf', 0, ['wght' => INF]))->toThrow(TypographyException::class, "'wght' is a finite number")
        ->and(fn () => new FontSource('/f.ttf', 0, ['wght' => NAN]))->toThrow(TypographyException::class, "'wght' is a finite number")
        ->and(fn () => new FontSource('', 0))->toThrow(TypographyException::class, 'names a file')
        ->and(fn () => new FontSource('/f.ttf', -1))->toThrow(TypographyException::class, 'got -1');
});
