<?php

namespace VelvetGE\Glyphs;

use ReflectionException;
use Surface\Contracts\Rendering\deprecated\Glyphs\GlyphPainter;
use Surface\Contracts\Rendering\deprecated\Rasterization\Edges;
use Surface\Rendering\deprecated\Glyphs\GlyphDrawingDriver;

/**
 * Velvet on Topside's glyphs manager: the driver registered there as 'velvet', which hands every
 * glyph painter to the module Velvet's configuration names (velvet.cpu.glyphs.default).
 */
class TopsideGlyphDriver extends GlyphDrawingDriver
{
    protected ?string $active_driver = null;

    public function __construct()
    {
        $this->active_driver = config('velvet.cpu.glyphs.default');
    }

    /**
     * The glyph painter Velvet's active module makes.
     *
     * @throws ReflectionException
     */
    public function glyphs(Edges $edges): GlyphPainter
    {
        /** @var VelvetGlyphManager $mgr */
        $mgr = app('velvet.glyphs');

        return $mgr->driver($this->active_driver)->glyphs($edges);
    }

    public function driver(): string
    {
        return 'velvet';
    }
}
