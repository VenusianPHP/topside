<?php

namespace Surface\Typography\Faces;

use Surface\Contracts\Typography\FontFace;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\TypographyException;

/**
 * A font face driver: a name and the faces it opens. A package that reads font files registers one
 * on Typography's faces manager (app('typography.faces')->extend()).
 */
abstract class FontFaceDriver
{
    /** The name it is registered under. */
    abstract public function driver(): string;

    /**
     * The source opened: one face a source, kept until release().
     *
     * @throws TypographyException When the file cannot be read as a font.
     */
    abstract public function face(FontSource $source): FontFace;

    /** Closes every face it opened. */
    abstract public function release(): void;
}
