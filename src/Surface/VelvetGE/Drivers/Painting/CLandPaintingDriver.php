<?php

namespace Surface\VelvetGE\Drivers\Painting;

use Surface\Contracts\Rendering\Painting\Brush;
use Surface\Contracts\Rendering\Rasterization\RasterizeDriver;
use Surface\Contracts\Rendering\Recording\RecordDriver;
use Surface\Contracts\Rendering\RenderingException;
use Surface\VelvetGE\Painters\CLandBrush;

/**
 * VelvetGE's painter with its brush in C: ext-paint paints shapes, gradient fills and clears into
 * 32-bit memory, and VelvetGE's painter does the rest. The pixels are the native driver's.
 */
class CLandPaintingDriver extends BrushPaintingDriver
{
    private ?CLandBrush $brush = null;

    /**
     * @throws RenderingException When ext-paint is not loaded: 'auto' then passes over this driver.
     */
    public function __construct(?RasterizeDriver $rasterize = null, bool $directFill = true, ?RecordDriver $record = null)
    {
        extension_loaded('paint') || throw new RenderingException('ext-paint is not loaded in this PHP.');
        parent::__construct($rasterize, $directFill, $record);
    }

    public function driver(): string
    {
        return 'ext';
    }

    public function brush(): Brush
    {
        return $this->brush ??= new CLandBrush();
    }
}
