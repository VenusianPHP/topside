<?php

namespace Surface\Contracts\Typography;

/**
 * Glyphs of one face at one size, placed: each glyph's origin on its baseline, from the top-left of
 * the typeset text, y down. This is what text is drawn from.
 */
final readonly class GlyphRun
{
    /**
     * @param  list<int>  $glyphs
     * @param  list<float>  $x
     * @param  list<float>  $y
     *
     * @throws TypographyException When the lists differ in length.
     */
    public function __construct(
        public FontSource $source,
        public float $size,
        public array $glyphs,
        public array $x,
        public array $y,
    ) {
        if (count($x) !== count($glyphs) || count($y) !== count($glyphs)) {
            throw new TypographyException('A glyph run has one position a glyph.');
        }
    }
}
