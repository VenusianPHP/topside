<?php

namespace Surface\Contracts\Typography;

/**
 * One face in one font file: the file's path, the face's index in it (0 unless the file is a
 * collection), and for a variable font the instance drawn, as its variation coordinates. Every driver
 * opens a source on its own, so a face one driver found can be shaped or drawn by another.
 *
 * Variations are OpenType axis tags and their values in the axis's own units ('wght' 200, 'wdth'
 * 100, 'opsz' 12), held sorted by tag. None means the font's default instance. A named instance is
 * the coordinates it names, so two ways of reaching the same instance are the same source.
 */
final readonly class FontSource
{
    /** @var array<string, float> axis tag => value, sorted by tag */
    public array $variations;

    /**
     * @param  array<string, int|float>  $variations  axis tag => value
     *
     * @throws TypographyException When the path is empty, the index negative, an axis tag not four
     *                             printable ASCII characters, or a value not finite.
     */
    public function __construct(
        public string $path,
        public int $index = 0,
        array $variations = [],
    ) {
        if ($path === '') {
            throw new TypographyException('A font source names a file.');
        }
        if ($index < 0) {
            throw new TypographyException("A font source's face index is 0 or more, got {$index}.");
        }
        $coordinates = [];
        foreach ($variations as $tag => $value) {
            if (preg_match('/^[\x20-\x7E]{4}$/', (string) $tag) !== 1) {
                throw new TypographyException(sprintf("A variation axis is a four-character OpenType tag, got '%s'.", $tag));
            }
            if (! is_finite((float) $value)) {
                throw new TypographyException("The variation '{$tag}' is a finite number.");
            }
            $coordinates[$tag] = (float) $value;
        }
        ksort($coordinates, SORT_STRING);
        $this->variations = $coordinates;
    }

    /** The file and face, and the coordinates when there are any: 'path#0', 'path#0@wdth=100,wght=200'. */
    public function key(): string
    {
        $key = "{$this->path}#{$this->index}";
        if ($this->variations === []) {
            return $key;
        }

        return $key.'@'.implode(',', array_map(
            fn (string $tag, float $value): string => "{$tag}={$value}",
            array_keys($this->variations),
            $this->variations,
        ));
    }
}
