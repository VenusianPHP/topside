<?php

namespace Surface\VelvetGE\Rasterize;

use RasterStroker;
use Surface\Contracts\Rendering\Rasterization\RasterizeException;
use Surface\Contracts\Rendering\Rasterization\StrokeCap;
use Surface\Contracts\Rendering\Rasterization\StrokeGeometry;
use Surface\Contracts\Rendering\Rasterization\StrokeJoin;

/**
 * VelvetGE's stroker in C: ext-rasterize's RasterStroker, the same steps as PHPStroker in the same order. A
 * dashed piece's points come back as floats, where PHPStroker leaves the first point of a piece as it was given.
 */
final class CLandStroker implements StrokeGeometry
{
    /**
     * @throws RasterizeException When ext-rasterize is not loaded, or has no RasterStroker.
     */
    public function __construct()
    {
        if (! class_exists(RasterStroker::class, false)) {
            throw RasterizeException::extensionMissing('ext', 'ext-rasterize with RasterStroker');
        }
    }

    public function outline(array $points, float $stroke, bool $closed, StrokeCap $cap = StrokeCap::BUTT, StrokeJoin $join = StrokeJoin::MITER, float $miterLimit = self::MITER_LIMIT, ?array $direction = null): array
    {
        return RasterStroker::outline(
            $points, $stroke, $closed,
            match ($cap) {
                StrokeCap::BUTT => RASTER_CAP_BUTT,
                StrokeCap::ROUND => RASTER_CAP_ROUND,
                StrokeCap::SQUARE => RASTER_CAP_SQUARE,
            },
            match ($join) {
                StrokeJoin::MITER => RASTER_JOIN_MITER,
                StrokeJoin::ROUND => RASTER_JOIN_ROUND,
                StrokeJoin::BEVEL => RASTER_JOIN_BEVEL,
            },
            $miterLimit, $direction,
        );
    }

    public function dashed(array $points, bool $closed, array $dash, float $offset): array
    {
        return RasterStroker::dashed($points, $closed, $dash, $offset);
    }
}
