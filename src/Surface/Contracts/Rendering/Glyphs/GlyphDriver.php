<?php

namespace Surface\Contracts\Rendering\Glyphs;

use Surface\Contracts\Rendering\Rasterization\Edges;

/**
 * Where glyphs are drawn: what a glyph library, or an extension, brings to draw text. A painter
 * records text as a 'glyphs' command (Surface\Contracts\Rendering\Recording\Recorder lists it) and
 * hands it to the glyph painter its driver made.
 */
interface GlyphDriver
{
    /** The name it is registered under. */
    public function driver(): string;

    /**
     * A glyph painter of its own for one painter, drawing with $edges. What it keeps of the glyphs
     * it has drawn, it keeps for as long as it lives, and nobody else draws from it.
     */
    public function glyphs(Edges $edges): GlyphPainter;
}
