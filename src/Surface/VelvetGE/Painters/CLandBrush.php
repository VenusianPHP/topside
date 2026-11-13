<?php

namespace Surface\VelvetGE\Painters;

use PaintSurface;
use Surface\Contracts\Rendering\Framebuffers\ChannelOrder;
use Surface\Contracts\Rendering\Framebuffers\FramebufferException;
use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Framebuffers\ScanDirection;
use Surface\Contracts\Rendering\Painting\Brush;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\RenderingException;
use Surface\NutsAndBolts\GradientSpread;
use Surface\Rendering\Rasterization\Rasterizer;
use Surface\VelvetGE\Drivers\Rasterization\PHPLandRasterizationDriver;
use Surface\VelvetGE\Framebuffers\Layout;
use ValueError;

/**
 * VelvetGE's brush in C: ext-paint paints shapes, gradient fills and clears straight into 32-bit
 * memory, a run of commands under one clip in one call. It scans and blends by the same steps as
 * VelvetGE's PHP scanner and store, so its pixels are theirs byte for byte.
 *
 * Shapes are prepared exactly as the rasterizer prepares them (Rasterizer::lower()): radii held,
 * a wide stroke turned into its outline. Layers, glass, images, glyphs and spans are VelvetGE's
 * painter's.
 */
class CLandBrush implements Brush
{
    /** The commands painted through PaintSurface::fill(), and where each holds its clip. */
    private const array SHAPES = ['path' => 4, 'polyline' => 5, 'ellipse' => 6, 'ring' => 7, 'roundrect' => 7, 'roundring' => 8, 'shadow' => 8, 'gradpath' => 4];

    /** The memory the surface was made over, and the surface. */
    private ?PixelMemory $over = null;

    private ?PaintSurface $surface = null;

    /** @var array<string, Rasterizer> by clip and edges: what lowers a run's shapes */
    private array $lowering = [];

    public function __construct()
    {
        extension_loaded('paint') || throw new RenderingException('ext-paint is not loaded in this PHP.');
    }

    /** Four bytes a pixel in any of the four channel orders, top row first: ext-paint writes those. */
    public function drawsInto(PixelMemory $memory): bool
    {
        try {
            return Layout::of($memory->format) === Layout::RGBA8888
                && $memory->format->scan_direction === ScanDirection::TOP_TO_BOTTOM
                && $memory->stride >= $memory->width * 4;
        } catch (FramebufferException) {
            return false;    // not a layout at all
        }
    }

    public function takes(array $command, Edges $edges): bool
    {
        return isset(self::SHAPES[$command[0]]) || $command[0] === 'clear';
    }

    public function paint(array $commands, Region $rows, PixelMemory $memory, Edges $edges): array
    {
        $surface = $this->surface($memory);
        $written = [];
        // a run of shapes under one clip is one fill()
        [$run, $clip] = [[], null];
        $flush = function () use (&$run, &$clip, &$written, $surface, $edges): void {
            if ($run !== []) {
                array_push($written, ...$this->fill($surface, $run, $clip, $edges));
                $run = [];
            }
        };

        foreach ($commands as $command) {
            if ($command[0] === 'clear') {
                $flush();
                $area = isset($command[2]) ? $command[2]->intersect($rows) : new Region(0, 0, $memory->width, $memory->height);
                if (! is_null($area)) {
                    $surface->clear($command[1], $area->x, $area->y, $area->width, $area->height);
                    $written[] = $area;
                }

                continue;
            }

            $cut = $command[self::SHAPES[$command[0]]]->intersect($rows);
            if (is_null($cut)) {
                continue;
            }
            if ($run !== [] && [$cut->x, $cut->y, $cut->width, $cut->height] !== [$clip->x, $clip->y, $clip->width, $clip->height]) {
                $flush();
            }
            $run[] = $command;
            $clip = $cut;
        }
        $flush();

        return $written;
    }

    /**
     * @param  list<array<int, mixed>>  $shapes  under one clip
     * @return list<Region|null>
     */
    private function fill(PaintSurface $surface, array $shapes, Region $clip, Edges $edges): array
    {
        $rasterizer = $this->lowering["{$clip->x},{$clip->y},{$clip->width},{$clip->height},{$edges->name}"] ??= (new PHPLandRasterizationDriver())->rasterizer($clip, $edges);
        [$ops, $paints] = [[], []];
        // plain shapes wait and are lowered together; a gradient fill between them keeps its place
        $plain = [];
        $lower = function () use (&$plain, &$ops, &$paints, $rasterizer): void {
            if ($plain !== []) {
                [$lowered, $colours] = $rasterizer->lower($plain);
                array_push($ops, ...$lowered);
                array_push($paints, ...$colours);
                $plain = [];
            }
        };
        foreach ($shapes as $shape) {
            if ($shape[0] !== 'gradpath') {
                $plain[] = $shape;

                continue;
            }
            $lower();
            // a gradient fill is a path with a gradient for its paint
            [$lowered] = $rasterizer->lower([['path', $shape[1], $shape[2], 0, $clip]]);
            if ($lowered !== []) {
                [$g, $m] = [$shape[3], $shape[3]['inverse']];
                $ops[] = $lowered[0];
                $paints[] = [
                    $g['kind'] === 'radial' ? \PAINT_RADIAL : \PAINT_LINEAR,
                    match ($g['spread']) {
                        GradientSpread::PAD => \PAINT_PAD,
                        GradientSpread::REPEAT => \PAINT_REPEAT,
                        GradientSpread::REFLECT => \PAINT_REFLECT,
                    },
                    $g['ramp'], $m->a, $m->b, $m->c, $m->d, $m->e, $m->f,
                ];
            }
        }
        $lower();
        if ($ops === []) {
            return [];
        }

        try {
            $boxes = $surface->fill($ops, $paints, $clip->x, $clip->y, $clip->width, $clip->height, $edges === Edges::ANTIALIASED);
        } catch (ValueError $e) {
            throw new RenderingException($e->getMessage(), previous: $e);
        }

        return array_map(fn (?array $box): ?Region => is_null($box) ? null : new Region(...$box), $boxes);
    }

    /** The surface over $memory, kept while the memory asked for is the same. */
    private function surface(PixelMemory $memory): PaintSurface
    {
        if (! is_null($this->over) && $this->over->address === $memory->address && $this->over->owner === $memory->owner
            && $this->over->width === $memory->width && $this->over->height === $memory->height && $this->over->stride === $memory->stride) {
            return $this->surface;
        }
        $order = match (Layout::channelOrder($memory->format)) {
            ChannelOrder::BGRA => \PAINT_ORDER_BGRA,
            ChannelOrder::ARGB => \PAINT_ORDER_ARGB,
            ChannelOrder::ABGR => \PAINT_ORDER_ABGR,
            default => \PAINT_ORDER_RGBA,
        };
        $this->over = $memory;
        $this->lowering = [];

        return $this->surface = PaintSurface::over($memory->address, $memory->width, $memory->height, $memory->stride, $order, $memory->owner);
    }
}
