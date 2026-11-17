<?php

namespace Surface\Contracts\Typography;

use Surface\NutsAndBolts\Path;

/**
 * One face of one font file, opened: its metrics, its character map and its glyphs. Glyph numbers
 * are the file's own, so every driver reading the same source agrees on them. Lengths are in font
 * units unless a size is given.
 */
interface FontFace
{
    public function source(): FontSource;

    /** Font units in one em. */
    public function unitsPerEm(): int;

    public function glyphCount(): int;

    /** Ascent, descent and line gap at a size, in drawing units. */
    public function metrics(float $size): FontMetrics;

    /** The glyph the face draws for a codepoint; 0 (the missing glyph) when it has none. */
    public function glyph(int $codepoint): int;

    /** How far the pen moves after the glyph, in font units. */
    public function advance(int $glyph): int;

    /**
     * The glyph's outline in font units, y up, from its origin on the baseline. Empty for a glyph
     * that draws nothing, such as a space.
     *
     * @throws TypographyException When the face's glyphs are not outlines this driver reads.
     */
    public function outline(int $glyph): Path;
}
