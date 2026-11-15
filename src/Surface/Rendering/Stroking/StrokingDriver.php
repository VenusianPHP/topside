<?php

namespace Surface\Rendering\Stroking;

use Surface\Contracts\Rendering\Rasterization\StrokeGeometry;

/**
 * A stroking driver: a name and the stroker it hands out. A package that brings a stroker registers
 * one on Rendering's strokers manager (app('strokers')->extend()).
 */
abstract class StrokingDriver
{
    /** The name it is registered under. */
    abstract public function driver(): string;

    /** Its stroker: one per driver, made on first use. */
    abstract public function stroker(): StrokeGeometry;
}
