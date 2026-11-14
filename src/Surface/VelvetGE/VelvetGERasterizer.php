<?php

namespace Surface\VelvetGE;

use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Rendering\Rasterization\RasterizeDriver;
use Surface\Graphics\GEFramebuffer;
use Surface\Graphics\GERasterizer;
use Surface\Graphics\GraphicsEngine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Path;
use Surface\Rendering\Rasterization\Stroker;

/** Velvet's rasterizer: Rendering's, from whichever rasterization driver the application has. */
class VelvetGERasterizer extends GERasterizer
{
    public function __construct(
        GraphicsEngine $engine,
        protected readonly RasterizeDriver $driver,
        protected readonly Edges $edges,
    ) {
        parent::__construct($engine);
    }

    public function edges(): Edges
    {
        return $this->edges;
    }

    protected function fillPath(GEFramebuffer $onto, Path $path, Color $color, FillRule $rule, Region $clip): void
    {
        $this->paint($onto, array_column($path->flatten(), 'points'), $color, $rule, $clip);
    }

    /** A stroke is filled as its outline, wound non-zero. */
    protected function strokePath(GEFramebuffer $onto, Path $path, Color $color, StrokeStyle $style, Region $clip): void
    {
        $contours = [];
        foreach ($path->flatten() as ['points' => $points, 'closed' => $closed]) {
            $pieces = $style->dash === []
                ? [['points' => $points, 'closed' => $closed, 'direction' => null]]
                : Stroker::dashed($points, $closed, $style->dash, $style->dashOffset);
            foreach ($pieces as $piece) {
                foreach (Stroker::outline($piece['points'], $style->width, $piece['closed'], $style->cap, $style->join, $style->miterLimit, $piece['direction']) as $flat) {
                    $contours[] = array_chunk($flat, 2);
                }
            }
        }
        $this->paint($onto, $contours, $color, FillRule::NON_ZERO, $clip);
    }

    /** @param list<list<array{float, float}>> $contours */
    private function paint(GEFramebuffer $onto, array $contours, Color $color, FillRule $rule, Region $clip): void
    {
        if ($contours === []) {
            return;
        }
        /** @var VelvetGEFramebuffer $onto the base checked it is this engine's */
        $this->driver->rasterizer($clip, $this->edges)->fillBatch([['path', $contours, $rule, VelvetGEFramebuffer::rgba($color)]], $onto->inner());
    }
}
