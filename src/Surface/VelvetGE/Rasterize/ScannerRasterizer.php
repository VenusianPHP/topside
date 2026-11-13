<?php

namespace Surface\VelvetGE\Rasterize;

use Closure;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\CompleteScanner;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Rendering\Rasterization\Rasterizer;

/**
 * A rasterizer over any scanner: the scanner a driver mints, completed with VelvetGE's own for
 * the tiers it leaves out. A DirectScanner paints its own ops straight into a target whose sink
 * it can paint into.
 */
class ScannerRasterizer extends Rasterizer
{
    use PaintsDirect;

    /**
     * @param string $driver the minting driver's name
     * @param Closure(Region, Edges): \Surface\Contracts\Rendering\Rasterization\Scanner $minter
     */
    public function __construct(
        private readonly string $driver,
        private readonly Closure $minter,
        Region $clip,
        Edges $edges,
    ) {
        parent::__construct($clip, $edges);
    }

    public function driver(): string
    {
        return $this->driver;
    }

    protected function mint(Region $clip, Edges $edges): CompleteScanner
    {
        return CompletingScanner::over(($this->minter)($clip, $edges));
    }
}
