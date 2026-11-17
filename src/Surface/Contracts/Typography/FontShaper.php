<?php

namespace Surface\Contracts\Typography;

/** Turns text into the glyphs of one face, positioned. */
interface FontShaper
{
    /**
     * The text as glyphs of the source at a size, left to right. The text is valid UTF-8 with no
     * line breaks; the typesetter sees to both.
     *
     * @throws TypographyException When the source cannot be read.
     */
    public function shape(string $text, FontSource $source, float $size): ShapedRun;
}
