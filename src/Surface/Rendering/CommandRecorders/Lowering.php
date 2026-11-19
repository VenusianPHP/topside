<?php

namespace Surface\Rendering\CommandRecorders;

use Surface\Contracts\Rendering\Enums\FillRule;
use ValueError;

/**
 * A recorded command in a simpler form, for a drawer whose library cannot draw it as it is. Every command is in
 * frame pixels already, so this reads nothing but the command. The same steps, in the same order, are ext-comrec's
 * src/lower.c: the two give the same numbers, bit for bit.
 *
 *   polygon     ['path', contours, rule, paint, clip]: curves as straight sides, a stroke as the outline it covers
 *   trapezoids  ['trapezoids', [[top, bottom, left at top, left at bottom, right at top, right at bottom], ...], paint, clip]
 *   triangles   ['triangles', [[x0, y0, x1, y1, x2, y2], ...], paint, clip]
 *
 * A stroke's outline has butt ends and mitered joins up to a miter of 4, beveled past it. 'clear', 'shadow' and
 * 'glyphs' have no lower form.
 *
 * @internal
 */
final class Lowering
{
    public const float MITER_LIMIT = 4.0;

    /** A band thinner than this is not cut again where its edges cross. */
    private const float THINNEST = 1e-9;

    /**
     * The command in $form; null when it has none. $packing gives a 'trapezoids' or 'triangles' command's items as
     * packed strings, one for each [encoding, fields] pair (see Recorder::lower()); a packed command lowers no further.
     *
     * @param list<array{string, list<int>}>|null $packing
     */
    public static function lower(array $command, string $form, ?array $packing = null): ?array
    {
        if (! in_array($form, ['polygon', 'trapezoids', 'triangles'], true)) {
            throw new ValueError("lower(): Argument #2 (\$form) must be 'polygon', 'trapezoids' or 'triangles'");
        }
        $packing === null || self::checkPacking($packing, 6);
        // lowered lists lower further: trapezoids to triangles. A packed form is the last.
        $kind = $command[0] ?? null;
        if ($kind === 'trapezoids' || $kind === 'triangles') {
            if ($form !== 'triangles' || $kind !== 'trapezoids' || ! array_key_exists(2, $command) || ! array_key_exists(3, $command)
                || ($items = self::numbers($command[1] ?? null)) === null) {
                return null;
            }

            return ['triangles', self::items(self::triangles($items), $packing), $command[2], $command[3]];
        }
        $polygon = self::polygon($command);
        if ($polygon === null || $form === 'polygon') {
            return $polygon;
        }
        $traps = self::trapezoids($polygon[1], $polygon[2]);
        if ($form === 'trapezoids') {
            return ['trapezoids', self::items($traps, $packing), $polygon[3], $polygon[4]];
        }

        return ['triangles', self::items(self::triangles($traps), $packing), $polygon[3], $polygon[4]];
    }

    /** A packing as Recorder::lower() takes it, for items of $size numbers; a ValueError on argument 3 if it is not one. */
    private static function checkPacking(array $packing, int $size): void
    {
        $refuse = static fn (string $why) => throw new ValueError("lower(): Argument #3 (\$packing) $why");
        if ($packing === [] || count($packing) > 8 || ! array_is_list($packing)) {
            $refuse('must be a list of 1 to 8 [encoding, fields] pairs');
        }
        foreach ($packing as $entry) {
            if (! is_array($entry) || count($entry) !== 2 || ! array_key_exists(0, $entry) || ! array_key_exists(1, $entry)) {
                $refuse('must hold [encoding, fields] pairs');
            }
            [$encoding, $fields] = [$entry[0], $entry[1]];
            if ($encoding !== 'f32' && $encoding !== 'fixed16') {
                $refuse("must name each encoding 'f32' or 'fixed16'");
            }
            if (! is_array($fields) || $fields === [] || count($fields) > 64 || ! array_is_list($fields)) {
                $refuse('must give each packing a list of 1 to 64 fields');
            }
            foreach ($fields as $field) {
                if (! is_int($field) || $field < 0 || $field >= $size) {
                    $refuse('must give fields as places 0 to '.($size - 1).' in an item');
                }
            }
        }
    }

    /**
     * Items: the list as it is, or with a packing, one string for each [encoding, fields] pair holding, for every item,
     * its numbers at those places in that order, four bytes each, little-endian.
     */
    private static function items(array $items, ?array $packing): array
    {
        if ($packing === null) {
            return $items;
        }
        $strings = [];
        foreach ($packing as [$encoding, $fields]) {
            $flat = [];
            foreach ($items as $item) {
                foreach ($fields as $field) {
                    $flat[] = (float) $item[$field];
                }
            }
            $strings[] = $encoding === 'f32' ? pack('g*', ...$flat) : pack('V*', ...array_map(self::fixed16(...), $flat));
        }

        return $strings;
    }

    /** A number as 16.16 fixed point: times 65536, rounded half away from zero, held to int32. */
    private static function fixed16(float $v): int
    {
        $s = $v * 65536.0;
        $t = $s < 0 ? ceil($s) : floor($s);
        if (abs($s - $t) >= 0.5) {
            $t += $s < 0 ? -1.0 : 1.0;
        }

        return (int) max(-2147483648.0, min(2147483647.0, $t));
    }

    /** ['path', contours, rule, paint, clip], or null for a command with no outline. */
    private static function polygon(array $c): ?array
    {
        switch ($c[0] ?? null) {
            case 'path':
                return $c;
            case 'ellipse':
                return ['path', [self::ellipse($c[1], $c[2], $c[3], $c[4])], FillRule::NON_ZERO, $c[5], $c[6]];
            case 'ring':
                $contours = self::outlineStroke(self::ellipse($c[1], $c[2], $c[3], $c[4]), $c[5], true);

                return $contours === [] ? null : ['path', $contours, FillRule::NON_ZERO, $c[6], $c[7]];
            case 'roundrect':
                return ['path', [self::outline($c[1], $c[2], $c[3] - $c[1], $c[4] - $c[2], $c[5])], FillRule::NON_ZERO, $c[6], $c[7]];
            case 'roundring':
                [$x0, $y0, $x1, $y1, $radii, $inset] = [$c[1], $c[2], $c[3], $c[4], $c[5], $c[6]];
                $outer = self::outline($x0, $y0, $x1 - $x0, $y1 - $y0, $radii);
                if (! ($x1 - $x0 > 2 * $inset && $y1 - $y0 > 2 * $inset)) {
                    return ['path', [$outer], FillRule::NON_ZERO, $c[7], $c[8]];
                }
                $inner = [];
                foreach ($radii as $r) {
                    $r -= $inset;
                    $inner[] = $r > 0.0 ? $r : 0.0;
                }

                return ['path', [$outer, self::outline($x0 + $inset, $y0 + $inset, $x1 - $x0 - 2 * $inset, $y1 - $y0 - 2 * $inset, $inner)], FillRule::EVEN_ODD, $c[7], $c[8]];
            case 'polyline':
                $contours = self::outlineStroke($c[1], $c[2], $c[3]);

                return $contours === [] ? null : ['path', $contours, FillRule::NON_ZERO, $c[4], $c[5]];
            default:
                return null;
        }
    }

    /** As the recorder's: how many straight sides keep a circle of this radius within 0.1 of a pixel, 12..1024. */
    private static function sides(float $radius): int
    {
        $sides = ceil(M_PI * sqrt($radius / (2 * 0.1)) * 1.01);
        if (! ($sides < 1024.0)) {
            $sides = 1024.0;
        }
        if (! ($sides > 12.0)) {
            $sides = 12.0;
        }

        return (int) $sides;
    }

    private static function ellipse(float $cx, float $cy, float $rx, float $ry): array
    {
        $sides = self::sides($rx < $ry ? $ry : $rx);
        $points = [];
        for ($i = 0; $i < $sides; $i++) {
            $angle = 2 * M_PI * (float) $i / (float) $sides;
            $points[] = [$cx + $rx * cos($angle), $cy + $ry * sin($angle)];
        }

        return $points;
    }

    /** A rounded box's outline, clockwise from the top-left corner. $radii: top-left, top-right, bottom-right, bottom-left. */
    private static function outline(float $x, float $y, float $w, float $h, array $radii): array
    {
        $corners = [
            [$x + $radii[0], $y + $radii[0], $radii[0], M_PI],
            [$x + $w - $radii[1], $y + $radii[1], $radii[1], 1.5 * M_PI],
            [$x + $w - $radii[2], $y + $h - $radii[2], $radii[2], 0.0],
            [$x + $radii[3], $y + $h - $radii[3], $radii[3], 0.5 * M_PI],
        ];
        $points = [];
        foreach ($corners as [$cx, $cy, $r, $from]) {
            if ($r <= 0) {
                $points[] = [$cx, $cy];

                continue;
            }
            $steps = intdiv(self::sides($r) + 3, 4);
            for ($i = 0; $i <= $steps; $i++) {
                $angle = $from + 0.5 * M_PI * (float) $i / (float) $steps;
                $points[] = [$cx + $r * cos($angle), $cy + $r * sin($angle)];
            }
        }

        return $points;
    }

    /**
     * The area a stroke of $width along $points covers, as outlines to fill non-zero: the left side going forward,
     * then the left side of the way back (the right side), so an open stroke is one contour with butt ends and a
     * closed one is two. An outer corner is a miter (up to MITER_LIMIT) or a bevel; an inner corner goes through the
     * corner's point.
     */
    private static function outlineStroke(array $points, float $width, bool $closed): array
    {
        $pts = [];
        foreach ($points as [$x, $y]) {
            $last = $pts === [] ? null : $pts[count($pts) - 1];
            if ($last === null || $last[0] != $x || $last[1] != $y) {
                $pts[] = [$x, $y];
            }
        }
        if ($closed && count($pts) > 1 && $pts[0][0] == $pts[count($pts) - 1][0] && $pts[0][1] == $pts[count($pts) - 1][1]) {
            array_pop($pts);
        }
        if (count($pts) < 2 || ! ($width > 0)) {
            return [];
        }
        $half = $width / 2;
        $forward = self::side($pts, $half, $closed);
        $back = self::side(array_reverse($pts), $half, $closed);

        return $closed ? [$forward, $back] : [array_merge($forward, $back)];
    }

    /** The points of a stroke's left side along $pts, with its corners. */
    private static function side(array $pts, float $half, bool $closed): array
    {
        $n = count($pts);
        $sides = $closed ? $n : $n - 1;
        $normals = [];
        $directions = [];
        for ($i = 0; $i < $sides; $i++) {
            [$ax, $ay] = $pts[$i];
            [$bx, $by] = $pts[($i + 1) % $n];
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $length = sqrt($dx * $dx + $dy * $dy);
            $normals[$i] = [-$dy / $length * $half, $dx / $length * $half];
            $directions[$i] = [$dx, $dy];
        }

        $out = [];
        if (! $closed) {
            $out[] = [$pts[0][0] + $normals[0][0], $pts[0][1] + $normals[0][1]];
        }
        [$from, $to] = $closed ? [0, $n] : [1, $n - 1];
        for ($j = $from; $j < $to; $j++) {
            $before = ($j - 1 + $sides) % $sides;
            $after = $j % $sides;
            [$px, $py] = $pts[$j];
            [$n0x, $n0y] = $normals[$before];
            [$n1x, $n1y] = $normals[$after];
            [$d0x, $d0y] = $directions[$before];
            [$d1x, $d1y] = $directions[$after];
            $turn = $d0x * $d1y - $d0y * $d1x;
            $out[] = [$px + $n0x, $py + $n0y];
            if ($turn > 0) {
                // turning towards this side: the inner corner, through the corner's point
                $out[] = [$px, $py];
            } elseif ($turn < 0) {
                // turning away from it: the outer corner, mitered while the miter is short enough
                $sx = $n0x + $n1x;
                $sy = $n0y + $n1y;
                $cos = sqrt($sx * $sx + $sy * $sy) / (2 * $half);
                if ($cos > 0 && 1 / $cos <= self::MITER_LIMIT) {
                    $k = 1 / (2 * $cos * $cos);
                    $out[] = [$px + $sx * $k, $py + $sy * $k];
                }
            } elseif ($d0x * $d1x + $d0y * $d1y < 0) {
                // straight back the way it came: around the end, through the point
                $out[] = [$px, $py];
            }
            if ($turn != 0.0 || $d0x * $d1x + $d0y * $d1y < 0) {
                $out[] = [$px + $n1x, $py + $n1y];
            }
        }
        if (! $closed) {
            $last = $sides - 1;
            $out[] = [$pts[$n - 1][0] + $normals[$last][0], $pts[$n - 1][1] + $normals[$last][1]];
        }

        return $out;
    }

    /**
     * The area inside $contours under $rule, as trapezoids with flat tops and bottoms: bands between the corners'
     * heights, cut again where two edges cross, and in each band the spans the rule fills.
     */
    private static function trapezoids(array $contours, FillRule $rule): array
    {
        $edges = [];
        $heights = [];
        foreach ($contours as $points) {
            $n = count($points);
            for ($i = 0; $i < $n; $i++) {
                [$ax, $ay] = $points[$i];
                [$bx, $by] = $points[($i + 1) % $n];
                if ($ay == $by) {
                    continue;
                }
                $edges[] = $ay < $by ? [$ax, $ay, $bx, $by, 1] : [$bx, $by, $ax, $ay, -1];
                $heights[] = $ay;
                $heights[] = $by;
            }
        }
        if ($edges === []) {
            return [];
        }
        sort($heights, SORT_NUMERIC);
        // the edges by where they start, entering the sweep as it reaches them
        $starts = array_keys($edges);
        usort($starts, static fn (int $a, int $b): int => $edges[$a][1] <=> $edges[$b][1] ?: $a <=> $b);
        $next = 0;
        $live = [];
        // the trapezoids still open, left to right: [left edge, right edge, top, left x at top, right x at top]
        $sweep = ['edges' => $edges, 'open' => [], 'traps' => []];
        $previous = null;
        foreach ($heights as $y) {
            if ($previous !== null && $y > $previous) {
                while ($next < count($starts) && $edges[$starts[$next]][1] <= $previous) {
                    $live[$starts[$next]] = true;
                    $next++;
                }
                foreach ($live as $index => $_) {
                    if ($edges[$index][3] <= $previous) {
                        unset($live[$index]);
                    }
                }
                $candidates = [];
                foreach ($live as $index => $_) {
                    $candidates[$index] = $edges[$index];
                }
                self::band($candidates, $previous, $y, $rule === FillRule::EVEN_ODD, $sweep);
            }
            $previous = $y;
        }
        self::close($sweep, $previous, []);

        return $sweep['traps'];
    }

    /** Where $edge is at height $y. */
    private static function at(array $edge, float $y): float
    {
        [$x0, $y0, $x1, $y1] = $edge;

        return $x0 + ($y - $y0) * (($x1 - $x0) / ($y1 - $y0));
    }

    /**
     * Settles the open trapezoids at height $y against $spans, the next band's [left edge, right edge, left x, right x]
     * left to right: one bounded by the same two edges carries on, the rest end at $y, and the new ones open there.
     */
    private static function close(array &$sweep, float $y, array $spans): void
    {
        $carried = [];
        foreach ($spans as [$left, $right]) {
            $open = $sweep['open'][$left] ?? null;
            if ($open !== null && $open[1] === $right) {
                $carried[$left] = true;
            }
        }
        foreach ($sweep['open'] as $left => [, $right, $top, $lt, $rt]) {
            if (! isset($carried[$left])) {
                $sweep['traps'][] = [$top, $y, $lt, self::at($sweep['edges'][$left], $y), $rt, self::at($sweep['edges'][$right], $y)];
            }
        }
        $open = [];
        foreach ($spans as [$left, $right, $lt, $rt]) {
            $open[$left] = isset($carried[$left]) ? $sweep['open'][$left] : [$left, $right, $y, $lt, $rt];
        }
        $sweep['open'] = $open;
    }

    /** The spans between $top and $bottom the rule fills, after cutting the band where its edges cross. */
    private static function band(array $edges, float $top, float $bottom, bool $evenOdd, array &$sweep): void
    {
        $middle = $top + ($bottom - $top) / 2;
        $active = [];
        foreach ($edges as $index => [$x0, $y0, $x1, $y1, $direction]) {
            if ($y0 <= $top && $y1 >= $bottom) {
                $slope = ($x1 - $x0) / ($y1 - $y0);
                $active[] = [$x0 + ($top - $y0) * $slope, $x0 + ($bottom - $y0) * $slope, $index, $direction, $x0 + ($middle - $y0) * $slope];
            }
        }
        // by where they are halfway down: their order all through a band no two of them cross in. Two that meet at the
        // band's top or bottom (a band cut where they cross) are not ordered by the last bit of where they meet.
        usort($active, static fn (array $a, array $b): int => $a[4] <=> $b[4] ?: $a[2] <=> $b[2]);

        $sealed = $evenOdd ? [] : self::sealed($active);
        $cut = null;
        for ($i = 0, $n = count($active) - 1; $i < $n; $i++) {
            if (isset($sealed[$active[$i][2]]) && $sealed[$active[$i][2]] === ($sealed[$active[$i + 1][2]] ?? null)) {
                continue;
            }
            [$lt, $lb] = [$active[$i][0], $active[$i][1]];
            [$rt, $rb] = [$active[$i + 1][0], $active[$i + 1][1]];
            if ($lt > $rt || $lb > $rb) {
                $apart = ($rt - $lt) - ($rb - $lb);
                if ($apart == 0.0) {
                    continue;
                }
                $y = $top + ($rt - $lt) / $apart * ($bottom - $top);
                if ($y - $top > self::THINNEST && $bottom - $y > self::THINNEST && ($cut === null || $y < $cut)) {
                    $cut = $y;
                }
            }
        }
        if ($cut !== null) {
            self::band($edges, $top, $cut, $evenOdd, $sweep);
            self::band($edges, $cut, $bottom, $evenOdd, $sweep);

            return;
        }

        $spans = [];
        $winding = 0;
        $left = null;
        foreach ($active as $edge) {
            $inside = $evenOdd ? ($winding & 1) === 1 : $winding !== 0;
            $winding += $edge[3];
            $now = $evenOdd ? ($winding & 1) === 1 : $winding !== 0;
            if (! $inside && $now) {
                $left = $edge;
            } elseif ($inside && ! $now) {
                $spans[] = [$left[2], $edge[2], $left[0], $edge[0]];
            }
        }
        self::close($sweep, $top, $spans);
    }

    /**
     * Under non-zero, the edges of a band (sorted halfway down) whose crossings cannot change what is filled, each by
     * the place in the band of the group it is in. A group is the edges between two places where the edges left of
     * them are the same at the band's top and at its bottom: they reorder only among themselves. If no order of them
     * brings the winding between two of them to 0 (the winding left of the group, plus all of their downs at least
     * and all of their ups at most), the area between them stays filled throughout, and they cross unseen.
     *
     * @param list<array{0: float, 1: float, 2: int, 3: int, 4: float}> $active
     * @return array<int, int> edge index => group
     */
    private static function sealed(array $active): array
    {
        $n = count($active);
        if ($n < 2) {
            return [];
        }
        $top = $bottom = range(0, $n - 1);
        // where they are just below the top and just above the bottom: by their x there, then halfway, then index
        usort($top, static fn (int $a, int $b): int => $active[$a][0] <=> $active[$b][0] ?: $active[$a][4] <=> $active[$b][4] ?: $active[$a][2] <=> $active[$b][2]);
        usort($bottom, static fn (int $a, int $b): int => $active[$a][1] <=> $active[$b][1] ?: $active[$a][4] <=> $active[$b][4] ?: $active[$a][2] <=> $active[$b][2]);
        $rank = [];
        foreach ($bottom as $place => $k) {
            $rank[$k] = $place;
        }
        $sealed = [];
        [$start, $reach, $left, $downs, $ups] = [0, -1, 0, 0, 0];
        foreach ($top as $place => $k) {
            $reach = max($reach, $rank[$k]);
            $direction = $active[$k][3];
            $direction < 0 ? $downs += $direction : $ups += $direction;
            if ($reach !== $place) {
                continue;
            }
            // the edges at places $start..$place are a group
            if ($place > $start && ($left + $downs > 0 || $left + $ups < 0)) {
                for ($p = $start; $p <= $place; $p++) {
                    $sealed[$active[$top[$p]][2]] = $start;
                }
            }
            $left += $downs + $ups;
            [$start, $downs, $ups] = [$place + 1, 0, 0];
        }

        return $sealed;
    }

    /** A list of trapezoids, each with numbers at 0 to 5, as floats; null when it is not one. */
    private static function numbers(mixed $list): ?array
    {
        if (! is_array($list)) {
            return null;
        }
        $traps = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                return null;
            }
            $trap = [];
            for ($k = 0; $k < 6; $k++) {
                $v = $item[$k] ?? null;
                if (! is_int($v) && ! is_float($v)) {
                    return null;
                }
                $trap[] = (float) $v;
            }
            $traps[] = $trap;
        }

        return $traps;
    }

    /** Each trapezoid as up to two triangles, without the ones with no area. */
    private static function triangles(array $traps): array
    {
        $triangles = [];
        foreach ($traps as [$top, $bottom, $lt, $lb, $rt, $rb]) {
            if ($rt != $lt) {
                $triangles[] = [$lt, $top, $rt, $top, $rb, $bottom];
            }
            if ($rb != $lb) {
                $triangles[] = [$lt, $top, $rb, $bottom, $lb, $bottom];
            }
        }

        return $triangles;
    }
}
