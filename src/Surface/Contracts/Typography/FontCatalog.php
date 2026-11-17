<?php

namespace Surface\Contracts\Typography;

/** Finds faces: the one a request names, and the one to fall back on for a character it lacks. */
interface FontCatalog
{
    /**
     * The face that best answers the request: its first family the catalog has, a generic family
     * resolved to the families it stands for, the weight and slant nearest the request's.
     *
     * @throws TypographyException When the catalog has no face at all.
     */
    public function match(FontRequest $request): FontSource;

    /** The face nearest the request that has a glyph for the codepoint; null when no face has one. */
    public function covering(FontRequest $request, int $codepoint): ?FontSource;
}
