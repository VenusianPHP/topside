<?php

namespace VelvetGE\Glyphs;

use Surface\Contracts\Graphics\deprecated2\DrawingException;
use Surface\Contracts\Rendering\deprecated\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Region;
use Surface\Contracts\Rendering\deprecated\Glyphs\GlyphPainter;
use Surface\Contracts\Typography\GlyphRun;
use Surface\NutsAndBolts\Affine;
use Surface\Typography\Faces\FontFaceDriver;
use VelvetGE\Contracts\Glyphs\GlyphStore;

/**
 * VelvetGE's glyph painter: the cache over a module's GlyphStore. A glyph is kept by its face (the
 * source's key, variation coordinates and all), its size in pixels and its number. On a miss it asks
 * the run's FontFace for the glyph's outline, puts it through the size and the transform, flattens
 * it within a quarter pixel and hands the store the contours; every later draw of that glyph at that
 * size only places it.
 *
 * Each glyph lands with its origin on the whole pixel nearest where the transform puts it, so one
 * kept glyph serves every place it is drawn. A transform that turns, shears or scales x and y apart
 * keeps its glyphs apart from upright ones: its linear part is part of their key.
 *
 * The store holds the glyphs; when they take more than the budget, after a draw, the faces and sizes
 * drawn longest ago are dropped until they fit again. What one draw needs is never dropped by it.
 */
final class GlyphCache implements GlyphPainter
{
    /** Contours are flattened to stray from the outline by less than this many pixels, as the recorder flattens paths. */
    private const float FLATNESS = 0.25;

    /** A glyph's box is at most this many pixels a side: the scanners' limit. */
    private const int SIDE = 65535;

    /** @var array<string, array<int, array{int, int, int}|false>> by face and size: glyph => [number in the store, left, top], false for a glyph with no ink */
    private array $glyphs = [];

    /** @var array<string, int> by face and size: the draw that last used it */
    private array $used = [];

    /** @var array<string, float> by source key: 1 / units per em */
    private array $perUnit = [];

    private int $draws = 0;

    /**
     * @param  FontFaceDriver|null  $faces  What opens the runs' faces; null is Typography's default face driver.
     * @param  int  $budget  The bytes the store may hold after a draw.
     */
    public function __construct(
        private readonly GlyphStore $store,
        private ?FontFaceDriver $faces = null,
        private readonly int $budget = 8 * 1024 * 1024,
    ) {}

    public function store(): GlyphStore
    {
        return $this->store;
    }

    /** How many glyphs are kept, inkless ones included. */
    public function count(): int
    {
        return array_sum(array_map('count', $this->glyphs));
    }

    public function drawsInto(InstanceThatManagesAFramebuffer $target): bool
    {
        return $this->store->drawsInto($target);
    }

    public function draw(array $command, Region $clip, InstanceThatManagesAFramebuffer $target): array
    {
        if (! $this->store->drawsInto($target)) {
            throw new DrawingException('The glyph store ('.$this->store::class.') does not draw into this framebuffer ('.$target::class.'); swap in a glyph module that does.');
        }
        [, $runs, [$a, $b, $c, $d, $e, $f], $rgba] = $command;
        $upright = $b == 0.0 && $c == 0.0 && $a == $d && $a > 0.0;
        $draw = ++$this->draws;

        $placed = [];
        foreach ($runs as $run) {
            /** @var GlyphRun $run */
            $source = $run->source->key();
            $key = $upright ? $source.'|'.($run->size * $a) : $source.'|'.$run->size.'|'.$a.','.$b.','.$c.','.$d;
            $this->used[$key] = $draw;
            $kept = &$this->glyphs[$key];
            $kept ??= [];
            foreach ($run->glyphs as $i => $glyph) {
                $entry = $kept[$glyph] ??= $this->make($run, $glyph, $a, $b, $c, $d);
                if ($entry === false) {
                    continue;
                }
                $x = $run->x[$i];
                $y = $run->y[$i];
                $placed[] = $entry[0];
                $placed[] = (int) floor($a * $x + $c * $y + $e + 0.5) + $entry[1];
                $placed[] = (int) floor($b * $x + $d * $y + $f + 0.5) + $entry[2];
            }
            unset($kept);
        }

        $written = $placed === [] ? [] : $this->store->paint($placed, $rgba, $clip, $target);
        if ($this->store->size() > $this->budget) {
            $this->shed($draw);
        }

        return $written;
    }

    /**
     * One glyph handed to the store: its outline in font units, y up, through the run's size and the
     * transform's linear part, flattened, and moved into a box from (0, 0).
     *
     * @return array{int, int, int}|false [number, left, top] from the glyph's origin; false when it has no ink
     */
    private function make(GlyphRun $run, int $glyph, float $a, float $b, float $c, float $d): array|false
    {
        $face = ($this->faces ??= app('typography.faces')->driver())->face($run->source);
        $k = $run->size * ($this->perUnit[$run->source->key()] ??= 1.0 / $face->unitsPerEm());
        $outline = $face->outline($glyph);
        if ($outline->isEmpty()) {
            return false;
        }
        // font units are y up; the text is y down
        $subpaths = $outline->transformed(new Affine($a * $k, $b * $k, -$c * $k, -$d * $k, 0.0, 0.0))->flatten(self::FLATNESS);

        [$x0, $y0, $x1, $y1] = [INF, INF, -INF, -INF];
        $kept = [];
        foreach ($subpaths as ['points' => $points]) {
            if (count($points) < 3) {
                continue;
            }
            $kept[] = $points;
            foreach ($points as [$x, $y]) {
                $x0 = min($x0, $x);
                $y0 = min($y0, $y);
                $x1 = max($x1, $x);
                $y1 = max($y1, $y);
            }
        }
        if ($kept === []) {
            return false;
        }
        $left = (int) floor($x0);
        $top = (int) floor($y0);
        $width = max(1, (int) ceil($x1) - $left);
        $height = max(1, (int) ceil($y1) - $top);
        if ($width > self::SIDE || $height > self::SIDE) {
            throw new DrawingException("Glyph {$glyph} of {$run->source->key()} is {$width} x {$height} pixels at size {$run->size}: past the ".self::SIDE.' a side a glyph can be.');
        }

        $contours = [];
        foreach ($kept as $points) {
            $flat = [];
            foreach ($points as [$x, $y]) {
                $flat[] = $x - $left;
                $flat[] = $y - $top;
            }
            $contours[] = $flat;
        }
        $number = $this->store->keep($contours, $width, $height);

        return is_null($number) ? false : [$number, $left, $top];
    }

    /** The faces and sizes drawn longest ago dropped, until the store fits its budget; none that $draw used. */
    private function shed(int $draw): void
    {
        asort($this->used);
        foreach ($this->used as $key => $last) {
            if ($last === $draw || $this->store->size() <= $this->budget) {
                break;
            }
            foreach ($this->glyphs[$key] as $entry) {
                if ($entry !== false) {
                    $this->store->forget($entry[0]);
                }
            }
            unset($this->glyphs[$key], $this->used[$key]);
        }
    }
}
