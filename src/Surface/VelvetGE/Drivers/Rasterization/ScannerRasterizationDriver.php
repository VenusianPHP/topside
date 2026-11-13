<?php

namespace Surface\VelvetGE\Drivers\Rasterization;

use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\Scanner;
use Surface\Rendering\Rasterization\RasterizationDriver;
use Surface\VelvetGE\Rasterize\ScannerRasterizer;

/**
 * A rasterization driver that only says how its scanner is made. Strokes, joins, validation and
 * every op the scanner leaves out come from VelvetGE. A library binding is this class and a
 * scanner:
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
 *             return new PlutoVGScanner($clip, $edges);    // Scanner alone is enough; add tiers it can do
 *         }
 *     }
 *
 * registered in its provider's boot():
 *
 *     Rasterization::manager()->extend('plutovg', fn () => new PlutoVGRasterizationDriver());
 *
 * A scanner that also implements DirectScanner paints a batch straight into a framebuffer whose
 * spanSink() it knows (its sister store's native image, say), with no span bytes in between.
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
