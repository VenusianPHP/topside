<?php

declare(strict_types=1);

use Surface\Contracts\Typography\FontSlant;
use Surface\Contracts\Typography\TypographyException;
use Surface\Typography\FontRegistry;

/* The fonts an application names in config/typography.php, made into Fonts. */

function registry(array $fonts, ?string $default = 'body', string $base = '/app'): FontRegistry
{
    return new FontRegistry($fonts, $default, fn (string $path) => "{$base}/{$path}");
}

it('makes a named font from its families, weight, slant and size, and the default one when none is named', function (): void {
    $fonts = registry([
        'body' => ['families' => ['Inter', 'sans-serif'], 'size' => 14],
        'quote' => ['families' => 'Georgia', 'weight' => 300, 'slant' => 'italic', 'size' => 18.5],
    ]);
    $quote = $fonts->font('quote');

    expect($fonts->font()->request->families)->toBe(['Inter', 'sans-serif'])
        ->and($fonts->font()->size)->toBe(14.0)
        ->and([$quote->request->families, $quote->request->weight, $quote->request->slant, $quote->size])
        ->toBe([['Georgia'], 300, FontSlant::ITALIC, 18.5])
        ->and($fonts->font('quote'))->toBe($quote);
});

it('pins a font to a file, a relative one under the base path', function (): void {
    $base = sys_get_temp_dir().'/registry-'.getmypid();
    @mkdir("{$base}/fonts", 0777, true);
    touch("{$base}/fonts/Title.ttc");
    $font = registry(['title' => ['file' => 'fonts/Title.ttc', 'index' => 2, 'size' => 28]], base: $base)->font('title');
    unlink("{$base}/fonts/Title.ttc");
    rmdir("{$base}/fonts");
    rmdir($base);

    expect([$font->source?->path, $font->source?->index])->toBe(["{$base}/fonts/Title.ttc", 2]);
});

it('refuses a name it does not have, an entry without a size, a slant it does not know, and a file that is not there', function (): void {
    $fonts = registry([
        'sizeless' => ['families' => ['Inter']],
        'leaning' => ['slant' => 'sideways', 'size' => 12],
        'missing' => ['file' => 'fonts/None.ttf', 'size' => 12],
    ]);

    expect(fn () => $fonts->font('nope'))->toThrow(TypographyException::class, "no font named 'nope'")
        ->and(fn () => $fonts->font('sizeless'))->toThrow(TypographyException::class, 'no numeric size')
        ->and(fn () => $fonts->font('leaning'))->toThrow(TypographyException::class, 'sideways')
        ->and(fn () => $fonts->font('missing'))->toThrow(TypographyException::class, 'does not exist');
});
