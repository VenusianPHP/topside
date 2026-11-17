<?php

namespace Surface\Rendering\Glyphs;

use ReflectionException;
use Surface\Rendering\LibraryManager;

/** The glyph drivers packages register: rendering.glyphs.default names the one in use. */
class GlyphLibraryManager extends LibraryManager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('rendering.glyphs.default');
    }
}
