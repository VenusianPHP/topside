<?php

namespace Surface\VelvetGE\Rasterize;

use RasterScanner;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\BatchScanner;
use Surface\Contracts\Rendering\Rasterization\CompleteScanner;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Rendering\Rasterization\PixelScanner;
use Surface\Contracts\Rendering\Rasterization\RasterizeException;
use Surface\Contracts\Rendering\Rasterization\Scanner;
use Surface\Contracts\Rendering\Rasterization\ShapeScanner;

/**
 * Any scanner, with every tier. An op the scanner has goes straight to it. An op from a tier it
 * leaves out is scanned by VelvetGE's own scanner over the same clip and edges: ext-rasterize's
 * when it is loaded, the PHP one otherwise. Both answer the same bytes, so a completed op does
 * not depend on which.
 */
final class CompletingScanner implements CompleteScanner
{
    private ?CompleteScanner $velvet = null;

    public function __construct(
        private readonly Scanner $scanner,
    ) {}

    /** $scanner as it is when it already has every tier, completed otherwise. */
    public static function over(Scanner $scanner): CompleteScanner
    {
        return $scanner instanceof CompleteScanner ? $scanner : new self($scanner);
    }

    /** The scanner being completed. */
    public function scanner(): Scanner
    {
        return $this->scanner;
    }

    public function clip(): Region
    {
        return $this->scanner->clip();
    }

    public function edges(): Edges
    {
        return $this->scanner->edges();
    }

    public function path(array $contours, FillRule $rule): string
    {
        return $this->scanner->path($contours, $rule);
    }

    public function ellipse(float $cx, float $cy, float $rx, float $ry): string
    {
        return $this->shapes()->ellipse($cx, $cy, $rx, $ry);
    }

    public function ring(float $cx, float $cy, float $rx, float $ry, float $stroke): string
    {
        return $this->shapes()->ring($cx, $cy, $rx, $ry, $stroke);
    }

    public function roundRect(float $x0, float $y0, float $x1, float $y1, array $radii): string
    {
        return $this->shapes()->roundRect($x0, $y0, $x1, $y1, $radii);
    }

    public function roundRing(float $x0, float $y0, float $x1, float $y1, array $radii, float $inset): string
    {
        return $this->shapes()->roundRing($x0, $y0, $x1, $y1, $radii, $inset);
    }

    public function shadow(float $x0, float $y0, float $x1, float $y1, float $radius, float $blur): string
    {
        return $this->pixels()->shadow($x0, $y0, $x1, $y1, $radius, $blur);
    }

    public function polyline(array $points, bool $closed): string
    {
        return $this->pixels()->polyline($points, $closed);
    }

    /** One call when the scanner has every op the batch holds; op by op otherwise, each through the tier that has it. */
    public function batch(array $ops): array
    {
        if ($this->scanner instanceof BatchScanner && $this->has($ops)) {
            return $this->scanner->batch($ops);
        }

        return array_map(fn (mixed $op): string => match (is_array($op) ? ($op[0] ?? null) : null) {
            self::PATH => $this->path($op[1], $op[2] === 1 ? FillRule::EVEN_ODD : FillRule::NON_ZERO),
            self::ELLIPSE => $this->ellipse($op[1], $op[2], $op[3], $op[4]),
            self::RING => $this->ring($op[1], $op[2], $op[3], $op[4], $op[5]),
            self::ROUND_RECT => $this->roundRect($op[1], $op[2], $op[3], $op[4], $op[5]),
            self::ROUND_RING => $this->roundRing($op[1], $op[2], $op[3], $op[4], $op[5], $op[6]),
            self::SHADOW => $this->shadow($op[1], $op[2], $op[3], $op[4], $op[5], $op[6]),
            self::POLYLINE => $this->polyline($op[1], $op[2]),
            default => throw new RasterizeException('An op is not a list that starts with a Scanner op.'),
        }, $ops);
    }

    /** Whether the scanner itself has every op in $ops. */
    private function has(array $ops): bool
    {
        foreach ($ops as $op) {
            if (! $this->owns($op)) {
                return false;
            }
        }

        return true;
    }

    /** Whether the scanner itself has $op, so nothing of it is left to VelvetGE. An op of no kind is left to batch() to refuse. */
    public function owns(mixed $op): bool
    {
        return match (is_array($op) ? ($op[0] ?? null) : null) {
            self::ELLIPSE, self::RING, self::ROUND_RECT, self::ROUND_RING => $this->scanner instanceof ShapeScanner,
            self::SHADOW, self::POLYLINE => $this->scanner instanceof PixelScanner,
            default => true,
        };
    }

    private function shapes(): ShapeScanner
    {
        return $this->scanner instanceof ShapeScanner ? $this->scanner : $this->velvet();
    }

    private function pixels(): PixelScanner
    {
        return $this->scanner instanceof PixelScanner ? $this->scanner : $this->velvet();
    }

    private function velvet(): CompleteScanner
    {
        return $this->velvet ??= class_exists(RasterScanner::class)
            ? new CLandScanner($this->clip(), $this->edges())
            : new PHPLandScanner($this->clip(), $this->edges());
    }
}
