<?php

namespace VelvetGE\Glyphs;

use Surface\Contracts\Rendering\deprecated\Rasterization\Edges;
use Surface\Rendering\deprecated\Glyphs\GlyphDrawingDriver;
use Surface\Typography\Faces\FontFaceDriver;
use VelvetGE\Contracts\Glyphs\GlyphStore;

/**
 * A glyph driver of VelvetGE: it says how its store is made, and that is all. Which glyphs are kept,
 * how each is made from its face's outline, where it lands and when it is dropped are VelvetGE's
 * (GlyphCache). A module's driver is this class and a store:
 *
 *     class PlutoGlyphDriver extends GlyphCacheDriver
 *     {
 *         public function driver(): string { return 'plutovg'; }
 *
 *         protected function store(Edges $edges): GlyphStore { return new PlutoGlyphStore($edges); }
 *     }
 *
 * registered on Velvet in its provider's boot(): app('velvet.glyphs')->extend('plutovg', fn () => new PlutoGlyphDriver()).
 */
abstract class GlyphCacheDriver extends GlyphDrawingDriver
{
    /** @param  FontFaceDriver|null  $faces  What opens the runs' faces; null is Typography's default face driver. */
    public function __construct(
        protected ?FontFaceDriver $faces = null,
    ) {}

    /** A store of its own for one painter, keeping glyphs with $edges. */
    abstract protected function store(Edges $edges): GlyphStore;

    public function glyphs(Edges $edges): GlyphCache
    {
        return new GlyphCache($this->store($edges), $this->faces);
    }
}
