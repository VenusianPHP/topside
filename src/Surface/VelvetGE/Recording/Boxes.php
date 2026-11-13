<?php

namespace Surface\VelvetGE\Recording;

use Surface\Contracts\Graphics\Effects\Displace;
use Surface\Contracts\Graphics\Effects\Glass;
use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\NutsAndBolts\Affine;

/**
 * Where commands write: a command's box, boxes merged into damage, two frames compared, a frame
 * cut to regions. The arithmetic every queue answers the same regions by.
 */
final class Boxes
{
    /**
     * The whole pixels a command can write, cut by its clip: its geometry, grown by a pixel for
     * anti-aliased edges, and for a stroke by the miter corners, which reach four half-strokes
     * out. Null when it writes nothing.
     *
     * @param  array<int, mixed>  $command
     */
    public static function box(array $command, Region $surface): ?Region
    {
        if ($command[0] === 'glass' && $command[5] !== null) {
            $own = self::box([...array_slice($command, 0, 5), null, $command[6]], $surface);
            $shadow = self::box($command[5], $surface);

            return $own === null ? $shadow : ($shadow === null ? $own : $own->union($shadow));
        }
        if ($command[0] === 'clear') {
            return $command[2] ?? $surface;
        }

        [$x0, $y0, $x1, $y1] = self::extent($command);
        $x0 = (int) max(floor($x0), 0);
        $y0 = (int) max(floor($y0), 0);
        $x1 = (int) min(ceil($x1), $surface->width);
        $y1 = (int) min(ceil($y1), $surface->height);
        if ($x1 <= $x0 || $y1 <= $y0) {
            return null;
        }

        return (new Region($x0, $y0, $x1 - $x0, $y1 - $y0))->intersect($command[array_key_last($command)]);
    }

    /**
     * How far a command's pixels reach before they are held to whole pixels, the surface and the
     * clip. Not for a 'clear', or a 'glass' that has a shadow: box() takes those apart first.
     *
     * @param  array<int, mixed>  $command
     * @return array{float, float, float, float} x0, y0, x1, y1
     */
    public static function extent(array $command): array
    {
        return match ($command[0]) {
            'path', 'gradpath' => self::around(array_merge(...$command[1]), 1.0),
            'polyline' => self::around($command[1], 2.0 * $command[2] + 1.0),
            'ellipse' => [$command[1] - $command[3] - 1.0, $command[2] - $command[4] - 1.0, $command[1] + $command[3] + 1.0, $command[2] + $command[4] + 1.0],
            'ring' => [$command[1] - $command[3] - $command[5] / 2 - 1.0, $command[2] - $command[4] - $command[5] / 2 - 1.0, $command[1] + $command[3] + $command[5] / 2 + 1.0, $command[2] + $command[4] + $command[5] / 2 + 1.0],
            'image' => self::around(self::placedCorners($command[1], $command[2], $command[5]['window'] ?? null), 1.0),
            'spans' => self::spansExtent($command[1]),
            'roundrect', 'roundring' => [$command[1] - 1.0, $command[2] - 1.0, $command[3] + 1.0, $command[4] + 1.0],
            'shadow' => [$command[1] - $command[6] - 1.0, $command[2] - $command[6] - 1.0, $command[3] + $command[6] + 1.0, $command[4] + $command[6] + 1.0],
            'glyphs' => [$command[5]->x, $command[5]->y, $command[5]->right(), $command[5]->bottom()],
            'layer', 'glass' => [$command[1]->x, $command[1]->y, $command[1]->right(), $command[1]->bottom()],
        };
    }

    /**
     * Snapped to the framebuffer's damage granularity, and merged until no two overlap: drawing a
     * frame into each region once draws no pixel twice.
     *
     * @param  list<Region>  $boxes
     * @return list<Region>
     */
    public static function merge(array $boxes, DamageGranularity $granularity): array
    {
        $merged = [];
        foreach ($boxes as $box) {
            $box = $box->snap($granularity);
            do {
                $grew = false;
                foreach ($merged as $index => $kept) {
                    if (! is_null($kept->intersect($box))) {
                        $box = $box->union($kept)->snap($granularity);
                        unset($merged[$index]);
                        $grew = true;
                    }
                }
            } while ($grew);
            $merged[] = $box;
        }

        return array_values($merged);
    }

    /**
     * Every command's box, in order; a command that writes nothing has none.
     *
     * @param  list<array<int, mixed>>  $commands
     * @return list<Region>
     */
    public static function drawn(array $commands, Region $surface): array
    {
        $boxes = [];
        foreach ($commands as $command) {
            if (! is_null($box = self::box($command, $surface))) {
                $boxes[] = $box;
            }
        }

        return $boxes;
    }

    /**
     * Where two frames differ, merged. Commands are compared by position: one equal to the
     * command at the same place in the frame before changed nothing. Every other command marks its
     * box, and the box of the command it replaced. An image is never equal, because its source may
     * hold new pixels.
     *
     * @param  list<array<int, mixed>>  $before
     * @param  list<array<int, mixed>>  $after
     * @return list<Region>
     */
    public static function changed(array $before, array $after, Region $surface, DamageGranularity $granularity): array
    {
        $boxes = [];
        for ($i = 0, $n = max(count($before), count($after)); $i < $n; $i++) {
            $old = $before[$i] ?? null;
            $new = $after[$i] ?? null;
            if (! is_null($old) && ! is_null($new) && ! self::restless($new) && $old == $new) {
                continue;
            }
            foreach ([$old, $new] as $command) {
                if (! is_null($command) && ! is_null($box = self::box($command, $surface))) {
                    $boxes[] = $box;
                }
            }
        }

        return self::merge($boxes, $granularity);
    }

    /**
     * A command whose pixels can change with nothing in it changing: an image, whose source may
     * hold new pixels, or a layer with an image or a displacement map in it.
     *
     * @param  array<int, mixed>  $command
     */
    public static function restless(array $command): bool
    {
        return $command[0] === 'image' || self::volatile($command);
    }

    /** A layer whose pixels can change with nothing in the command changing: an image or a displacement map inside it holds a framebuffer. */
    private static function volatile(array $command): bool
    {
        if ($command[0] !== 'layer') {
            return false;
        }
        foreach ([$command[5], $command[6]] as $chain) {
            if (! is_null($chain) && array_filter($chain->steps, fn ($step): bool => $step instanceof Displace) !== []) {
                return true;
            }
        }
        foreach ($command[3] as $child) {
            if ($child[0] === 'image' || self::volatile($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What a command reads under itself: a layer with a backdrop, or a pane of glass, reads the
     * pixels around its box as far as its chain reaches. Null for every other command.
     *
     * @param  array<int, mixed>  $command
     * @return array{Region, float}|null the command's area, and how many pixels past it are read
     */
    public static function reads(array $command): ?array
    {
        if ($command[0] === 'layer' && ! is_null($command[6])) {
            return [$command[1], $command[6]->reach()];
        }
        if ($command[0] === 'glass') {
            return [$command[1], (float) Glass::margin($command[4], $command[3][1])];
        }

        return null;
    }

    /**
     * A layer with a backdrop reads the pixels around its box, filters them and lays them back:
     * what it shows depends on everything under it up to the chain's reach. When any of that is
     * redrawn the whole read area is redrawn with it, and the area may reach the next layer's.
     *
     * @param  list<Region>  $regions
     * @param  list<array<int, mixed>>  $commands
     * @return list<Region>
     */
    public static function withBackdrops(array $regions, array $commands, Region $surface, DamageGranularity $granularity): array
    {
        $outers = [];
        foreach ($commands as $command) {
            $reads = self::reads($command);
            $outer = is_null($reads) ? null : self::grown($reads[0], $reads[1], $surface);
            if (! is_null($outer)) {
                $outers[] = $outer;
            }
        }
        do {
            $grew = false;
            foreach ($outers as $i => $outer) {
                foreach ($regions as $region) {
                    if (! is_null($region->intersect($outer))) {
                        $regions = self::merge([...$regions, $outer], $granularity);
                        unset($outers[$i]);
                        $grew = true;

                        continue 2;
                    }
                }
            }
        } while ($grew);

        return $regions;
    }

    /** The region grown by $reach pixels (rounded up) on every side, kept on the surface; null when none of it is on the surface. */
    public static function grown(Region $region, float $reach, Region $surface): ?Region
    {
        $by = (int) ceil($reach);

        return (new Region($region->x - $by, $region->y - $by, $region->width + 2 * $by, $region->height + 2 * $by))->intersect($surface);
    }

    /**
     * The frame drawn into each region alone: every clip cut to the region, a clear limited to
     * it, spans trimmed to it. Commands that miss a region are left out of it.
     *
     * @param  list<array<int, mixed>>  $commands
     * @param  list<Region>  $regions
     * @return list<array<int, mixed>>
     */
    public static function scissor(array $commands, array $regions): array
    {
        $out = [];
        foreach ($regions as $region) {
            foreach ($commands as $command) {
                if ($command[0] === 'clear') {
                    $area = isset($command[2]) ? $command[2]->intersect($region) : $region;
                    if (! is_null($area)) {
                        $out[] = ['clear', $command[1], $area];
                    }

                    continue;
                }

                if ($command[0] === 'glass') {
                    // the pane and its shadow, each cut to the region; a region that only reaches the shadow gets the shadow alone
                    $shadow = $command[5];
                    if ($shadow !== null) {
                        $shadow[8] = $shadow[8]->intersect($region);
                        $shadow = $shadow[8] === null ? null : $shadow;
                    }
                    $area = $command[6]->intersect($region);
                    if ($area === null) {
                        $shadow === null || $out[] = $shadow;

                        continue;
                    }
                    [$command[5], $command[6]] = [$shadow, $area];
                    $out[] = $command;

                    continue;
                }

                $last = array_key_last($command);
                $clip = $command[$last]->intersect($region);
                if (is_null($clip)) {
                    continue;
                }
                $command[$last] = $clip;
                if ($command[0] === 'spans') {
                    $command[1] = self::trimSpans($command[1], $clip);
                    if ($command[1] === '') {
                        continue;
                    }
                }
                $out[] = $command;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{float, float}>  $points
     * @return array{float, float, float, float} x0, y0, x1, y1 grown by $grow
     */
    private static function around(array $points, float $grow): array
    {
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return [min($xs) - $grow, min($ys) - $grow, max($xs) + $grow, max($ys) + $grow];
    }

    /**
     * @param  array{float, float, float, float}|null  $window  The part of the source that paints; null for all of it.
     * @return list<array{float, float}> An image source's corners (or the window's), placed.
     */
    private static function placedCorners(Framebuffer $source, Affine $placement, ?array $window = null): array
    {
        [$u0, $v0, $u1, $v1] = $window ?? [0.0, 0.0, (float) $source->viewportWidth(), (float) $source->viewportHeight()];

        return [$placement->apply($u0, $v0), $placement->apply($u1, $v0), $placement->apply($u1, $v1), $placement->apply($u0, $v1)];
    }

    /** @return array{float, float, float, float} The pixels a span list covers. */
    private static function spansExtent(string $spans): array
    {
        $x0 = $y0 = PHP_INT_MAX;
        $x1 = $y1 = 0;
        for ($at = 0, $length = strlen($spans); $at < $length; $at += 7) {
            $y = ord($spans[$at]) | (ord($spans[$at + 1]) << 8);
            $x = ord($spans[$at + 2]) | (ord($spans[$at + 3]) << 8);
            $right = $x + (ord($spans[$at + 4]) | (ord($spans[$at + 5]) << 8));
            if ($x < $x0) {
                $x0 = $x;
            }
            if ($right > $x1) {
                $x1 = $right;
            }
            if ($y < $y0) {
                $y0 = $y;
            }
            if ($y >= $y1) {
                $y1 = $y + 1;
            }
        }

        return [(float) $x0, (float) $y0, (float) $x1, (float) $y1];
    }

    /**
     * Spans cut to $region; a span outside it is dropped, one wholly inside is copied as it is.
     * The list runs top to bottom, so the first row inside is found by halving and the walk stops
     * below the region.
     */
    private static function trimSpans(string $spans, Region $region): string
    {
        $left = $region->x;
        $top = $region->y;
        $right = $region->right();
        $bottom = $region->bottom();

        $low = 0;
        $high = intdiv(strlen($spans), 7);
        while ($low < $high) {
            $middle = ($low + $high) >> 1;
            $at = $middle * 7;
            if ((ord($spans[$at]) | (ord($spans[$at + 1]) << 8)) < $top) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        $out = '';
        for ($at = $low * 7, $length = strlen($spans); $at < $length; $at += 7) {
            $y = ord($spans[$at]) | (ord($spans[$at + 1]) << 8);
            if ($y >= $bottom) {
                break;
            }
            $x = ord($spans[$at + 2]) | (ord($spans[$at + 3]) << 8);
            $end = $x + (ord($spans[$at + 4]) | (ord($spans[$at + 5]) << 8));
            if ($end <= $left || $x >= $right) {
                continue;
            }
            if ($x >= $left && $end <= $right) {
                $out .= substr($spans, $at, 7);

                continue;
            }
            $start = max($x, $left);
            $out .= pack('vvvC', $y, $start, min($end, $right) - $start, ord($spans[$at + 6]));
        }

        return $out;
    }
}
