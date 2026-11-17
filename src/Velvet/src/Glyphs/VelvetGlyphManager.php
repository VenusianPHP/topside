<?php

namespace VelvetGE\Glyphs;

use ReflectionException;
use Voyager\NutsAndBolts\Manager;

/**
 * Velvet's glyph modules, by name. Velvet ships none: each module's service provider registers its
 * drivers here with extend().
 */
class VelvetGlyphManager extends Manager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return config('velvet.cpu.glyphs.default', 'lustrous-php');
    }
}
