<?php

namespace Surface\Contracts\Rendering\Rasterization;

/**
 * A scanner with every tier. A Rasterizer works on one of these: the engine's
 * two scanners are complete, and any other scanner is completed before a
 * rasterizer sees it.
 */
interface CompleteScanner extends BatchScanner, PixelScanner, ShapeScanner {}
