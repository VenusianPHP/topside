<?php

namespace Surface\Rendering\Glyphs;

use Surface\Contracts\Rendering\Glyphs\GlyphDriver;

/**
 * A glyph driver. A package that draws glyphs registers one on Rendering's glyphs manager
 * (Glyphs::manager()->extend()); a constructor that throws RenderingException tells 'auto' it
 * cannot run here.
 */
abstract class GlyphDrawingDriver implements GlyphDriver
{
}
