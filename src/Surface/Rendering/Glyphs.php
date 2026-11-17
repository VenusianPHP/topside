<?php

namespace Surface\Rendering;

use ReflectionException;
use Surface\Rendering\Glyphs\GlyphDrawingDriver;
use Surface\Rendering\Glyphs\GlyphLibraryManager;

/** The glyph drivers: Glyphs::driver()->glyphs($edges). */
class Glyphs
{
    /**
     * @throws ReflectionException
     */
    public static function driver(?string $name = null): GlyphDrawingDriver
    {
        return static::manager()->driver($name);
    }

    /**
     * @throws ReflectionException
     */
    public static function manager(): GlyphLibraryManager
    {
        return app('glyphs');
    }
}
