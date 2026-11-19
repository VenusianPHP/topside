<?php

namespace VelvetGE\Rasterization;

use Closure;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Region;
use Surface\Contracts\Rendering\deprecated\Rasterization\Edges;
use Surface\Contracts\Rendering\deprecated\Rasterization\Scanner;
use VelvetGE\Contracts\Framebuffers\CompositingFramebuffer;

/**
 * VelvetGE's rasterizer: the one every rasterization driver mints, VelvetGE's own two and each
 * library's. It works over the driver's scanner alone, and it paints a batch through DirectDraw.
 */
class ScannerRasterizer extends Rasterizer
{
    /**
     * @param string $driver the minting driver's name
     * @param Closure(Region, Edges): \Surface\Contracts\Rendering\deprecated\Rasterization\Scanner $minter
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

    protected function mint(Region $clip, Edges $edges): Scanner
    {
        return ($this->minter)($clip, $edges);
    }

    public function fillBatch(array $shapes, CompositingFramebuffer $target, bool $direct = true): array
    {
        [$ops, $colours, $undrawn] = $this->lower($shapes);
        if ($ops !== []) {
            DirectDraw::shapes($this->scanner, $ops, $colours, $target, $direct);
        }

        return $undrawn;
    }
}
