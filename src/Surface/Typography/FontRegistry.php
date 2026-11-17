<?php

namespace Surface\Typography;

use Closure;
use Surface\Contracts\Typography\Font;
use Surface\Contracts\Typography\FontRequest;
use Surface\Contracts\Typography\FontSlant;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\TypographyException;

/**
 * The fonts an application names (config/typography.php 'fonts'): a name for each, made into a Font
 * on first use. An entry asks by families, weight and slant, or names a file:
 *
 *     'body'  => ['families' => ['Helvetica Neue', 'sans-serif'], 'size' => 14],
 *     'title' => ['file' => 'fonts/Inter-Bold.ttf', 'size' => 28, 'weight' => 700],
 *
 * A relative file is under the application's base path. A file's families, weight and slant still
 * choose the faces its missing characters fall back on.
 */
final class FontRegistry
{
    /** @var array<string, Font> */
    private array $made = [];

    /**
     * @param  array<string, array<string, mixed>>  $fonts
     * @param  Closure(string): string  $basePath
     */
    public function __construct(
        private readonly array $fonts,
        private readonly ?string $default,
        private readonly Closure $basePath,
    ) {}

    /**
     * The named font, or the default one.
     *
     * @throws TypographyException When there is no font by that name, or its entry is not one.
     */
    public function font(?string $name = null): Font
    {
        $name ??= $this->default;
        if (is_null($name)) {
            throw new TypographyException('No font was named and typography.default names none.');
        }
        if (! isset($this->fonts[$name])) {
            throw new TypographyException("There is no font named '{$name}' in typography.fonts.");
        }

        return $this->made[$name] ??= $this->make($name, $this->fonts[$name]);
    }

    public function has(string $name): bool
    {
        return isset($this->fonts[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->fonts);
    }

    /** @param  array<string, mixed>  $entry */
    private function make(string $name, array $entry): Font
    {
        if (! isset($entry['size']) || ! is_int($entry['size']) && ! is_float($entry['size'])) {
            throw new TypographyException("The font '{$name}' has no numeric size.");
        }
        $slant = FontSlant::tryFrom($entry['slant'] ?? 'upright')
            ?? throw new TypographyException("The font '{$name}' has slant '{$entry['slant']}': upright, italic or oblique.");
        $request = new FontRequest((array) ($entry['families'] ?? ['sans-serif']), (int) ($entry['weight'] ?? 400), $slant);
        $source = null;
        if (isset($entry['file'])) {
            $file = (string) $entry['file'];
            $path = str_starts_with($file, '/') ? $file : ($this->basePath)($file);
            if (! is_file($path)) {
                throw new TypographyException("The font '{$name}' names the file {$path}, which does not exist.");
            }
            $source = new FontSource($path, (int) ($entry['index'] ?? 0));
        }

        return new Font($request, (float) $entry['size'], $source);
    }
}
