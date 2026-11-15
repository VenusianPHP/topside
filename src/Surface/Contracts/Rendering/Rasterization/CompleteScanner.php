<?php

namespace Surface\Contracts\Rendering\Rasterization;

/**
 * A scanner with every tier. A rasterizer gives it every shape as the shape's
 * own op; a scanner without some tier gets curved shapes as outlines and hands
 * back what it has no op for (VelvetGE\Rasterization\Rasterizer).
 */
interface CompleteScanner extends BatchScanner, PixelScanner, ShapeScanner {}
