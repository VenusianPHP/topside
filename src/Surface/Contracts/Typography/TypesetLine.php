<?php

namespace Surface\Contracts\Typography;

/**
 * One line of typeset text, from the top-left of the text: where its box starts and its baseline
 * lies, how tall and wide it is, where it starts across, and its glyphs.
 */
final readonly class TypesetLine
{
    /** @param  list<GlyphRun>  $runs */
    public function __construct(
        public float $top,
        public float $baseline,
        public float $height,
        public float $left,
        public float $width,
        public array $runs,
    ) {}
}
