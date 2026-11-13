<?php

declare(strict_types=1);

namespace Venusian\Topside\Tests\Fixtures;

use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\BatchScanner;
use Surface\Contracts\Rendering\Rasterization\DirectScanner;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Rendering\Rasterization\Scanner;
use Surface\Contracts\Rendering\Rasterization\ShapeScanner;
use Surface\VelvetGE\Drivers\Rasterization\ScannerRasterizationDriver;
use Surface\VelvetGE\Rasterize\CLandScanner;
use Surface\VelvetGE\Rasterize\PHPLandScanner;

/** A library scanner at its least: it fills paths and nothing else. */
class CoreOnlyScanner implements Scanner
{
    protected PHPLandScanner $inner;

    /** @var list<string> every op this scanner itself answered */
    public static array $asked = [];

    public function __construct(Region $clip, Edges $edges)
    {
        $this->inner = new PHPLandScanner($clip, $edges);
    }

    public function clip(): Region
    {
        return $this->inner->clip();
    }

    public function edges(): Edges
    {
        return $this->inner->edges();
    }

    public function path(array $contours, FillRule $rule): string
    {
        self::$asked[] = 'path';

        return $this->inner->path($contours, $rule);
    }
}

/** A library scanner that has curves and a batch call, and no pixel tier: a batch holding a shadow cannot go to it whole. */
class ShapesAndBatchScanner extends CoreOnlyScanner implements BatchScanner, ShapeScanner
{
    public function ellipse(float $cx, float $cy, float $rx, float $ry): string
    {
        self::$asked[] = 'ellipse';

        return $this->inner->ellipse($cx, $cy, $rx, $ry);
    }

    public function ring(float $cx, float $cy, float $rx, float $ry, float $stroke): string
    {
        self::$asked[] = 'ring';

        return $this->inner->ring($cx, $cy, $rx, $ry, $stroke);
    }

    public function roundRect(float $x0, float $y0, float $x1, float $y1, array $radii): string
    {
        self::$asked[] = 'roundRect';

        return $this->inner->roundRect($x0, $y0, $x1, $y1, $radii);
    }

    public function roundRing(float $x0, float $y0, float $x1, float $y1, array $radii, float $inset): string
    {
        self::$asked[] = 'roundRing';

        return $this->inner->roundRing($x0, $y0, $x1, $y1, $radii, $inset);
    }

    public function batch(array $ops): array
    {
        self::$asked[] = 'batch';

        return $this->inner->batch($ops);
    }
}

/**
 * A library scanner that fills paths and paints them straight into an ext-fb buffer, and has no other tier:
 * a batch that mixes paths with other shapes is painted part direct, part through spans. Needs ext-rasterize
 * and ext-fb, which stand in for the library here.
 */
class DirectPathScanner extends CoreOnlyScanner implements DirectScanner
{
    public function paintsInto(object $sink): bool
    {
        return $sink instanceof \FbBuffer;
    }

    public function fill(array $ops, array $colours, object $sink): array
    {
        self::$asked[] = 'fill:'.count($ops);

        return (new CLandScanner($this->clip(), $this->edges()))->fill($ops, $colours, $sink);
    }
}

/**
 * A library scanner that knows nothing of ext-fb's buffer: it paints paths into any store's memory, given where
 * the bytes are. ext-rasterize over that memory stands in for the library's own drawing.
 */
class MemoryPathScanner extends CoreOnlyScanner implements DirectScanner
{
    public function paintsInto(object $sink): bool
    {
        return $sink instanceof PixelMemory;
    }

    public function fill(array $ops, array $colours, object $sink): array
    {
        self::$asked[] = 'fill:'.count($ops);

        return (new CLandScanner($this->clip(), $this->edges()))->fill($ops, $colours, $sink);
    }
}

/** A library binding's driver: a name and a scanner class. */
class PartialRasterizationDriver extends ScannerRasterizationDriver
{
    /** @param class-string<Scanner> $scanner */
    public function __construct(
        private readonly string $name,
        private readonly string $scanner,
    ) {}

    public function driver(): string
    {
        return $this->name;
    }

    protected function scanner(Region $clip, Edges $edges): Scanner
    {
        return new ($this->scanner)($clip, $edges);
    }
}
