<?php

namespace Surface\VelvetGE\Rasterize;

use Closure;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\CompleteScanner;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Rendering\Rasterization\Rasterizer;
use Surface\VelvetGE\DirectDraw;

/**
 * VelvetGE's rasterizer: the one every rasterization driver mints, VelvetGE's own two and each
 * library's. It works over the driver's scanner, completed with VelvetGE's for the tiers the
 * scanner leaves out, and it paints a batch through DirectDraw.
 */
class ScannerRasterizer extends Rasterizer
{
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

    public function fillBatch(array $shapes, Framebuffer $target, bool $direct = true): void
    {
        [$ops, $colours] = $this->lower($shapes);

        DirectDraw::shapes($this->scanner, $ops, $colours, $target, $direct);
    }
}
