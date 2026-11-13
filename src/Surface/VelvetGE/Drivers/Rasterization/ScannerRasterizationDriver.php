<?php

namespace Surface\VelvetGE\Drivers\Rasterization;

use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\Scanner;
use Surface\Rendering\Rasterization\RasterizationDriver;
use Surface\VelvetGE\Rasterize\ScannerRasterizer;

/**
 * A rasterization driver of VelvetGE: it says how its scanner is made, and that is all. Strokes,
 * joins, validation, every op the scanner leaves out and the way a batch reaches a framebuffer
 * come from VelvetGE. VelvetGE's own two drivers are this class and a scanner, and so is a
 * library binding:
 *
 *     class PlutoVGRasterizationDriver extends ScannerRasterizationDriver
 *     {
 *         public function __construct()
 *         {
 *             if (! extension_loaded('plutovg')) {
 *                 throw RasterizeException::extensionMissing('plutovg', 'ext-plutovg');   // 'auto' then tries the next driver
 *             }
 *         }
 *
 *         public function driver(): string { return 'plutovg'; }
 *
 *         protected function scanner(Region $clip, Edges $edges): Scanner
 *         {
 *             return new PlutoVGScanner($clip, $edges);
 *         }
 *     }
 *
 * registered in its provider's boot():
 *
 *     Rasterization::manager()->extend('plutovg', fn () => new PlutoVGRasterizationDriver());
 *
 * A scanner is Scanner (paths) and whichever of these it can also do, each an interface:
 * ShapeScanner, PixelScanner and BatchScanner for more ops; DirectScanner to blend straight into
 * a pixel store's memory; MaskScanner to answer coverage as bytes when it has no span list.
 * VelvetGE's DirectDraw picks the way for each framebuffer, so a scanner never names a store.
 *
 * A scanner may also answer VelvetGE's own for a clip or edges the library cannot do (hard edges,
 * say): return new PHPLandScanner($clip, $edges) from scanner() for those.
 */
abstract class ScannerRasterizationDriver extends RasterizationDriver
{
    abstract protected function scanner(Region $clip, Edges $edges): Scanner;

    public function rasterizer(Region $clip, Edges $edges): ScannerRasterizer
    {
        return new ScannerRasterizer($this->driver(), $this->scanner(...), $clip, $edges);
    }
}
