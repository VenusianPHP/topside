<?php

namespace Surface\Contracts\Typography;

/**
 * What a shaper makes of a piece of text: glyphs of one face at one size, each with the byte offset
 * in the text it came from, how far it moves the pen and where it sits from the pen. Lengths are in
 * drawing units; offsets are y up.
 */
final readonly class ShapedRun
{
    /** The sum of the advances. */
    public float $width;

    /**
     * @param  list<int>  $glyphs
     * @param  list<int>  $clusters  byte offsets, not decreasing
     * @param  list<float>  $advances
     * @param  list<float>  $offsetsX
     * @param  list<float>  $offsetsY
     *
     * @throws TypographyException When the lists differ in length.
     */
    public function __construct(
        public FontSource $source,
        public float $size,
        public array $glyphs,
        public array $clusters,
        public array $advances,
        public array $offsetsX,
        public array $offsetsY,
    ) {
        $count = count($glyphs);
        if (count($clusters) !== $count || count($advances) !== $count || count($offsetsX) !== $count || count($offsetsY) !== $count) {
            throw new TypographyException('A shaped run has one cluster, advance and offset a glyph.');
        }
        $this->width = array_sum($advances);
    }
}
