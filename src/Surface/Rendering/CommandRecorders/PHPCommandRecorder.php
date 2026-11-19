<?php

namespace Surface\Rendering\CommandRecorders;

use Error;
use ValueError;
use Surface\Contracts\Rendering\Enums\FillRule;
use Surface\Contracts\Rendering\Recording\Recorder;
use Surface\Contracts\Rendering\Recording\Enums\CommandReach;
use Surface\Contracts\Rendering\Recording\Enums\RecordingError;
use Surface\Contracts\Rendering\Recording\Exceptions\CommandRecorderException;

/**
 * The recorder in PHP. It takes the same steps as ext-comrec, in the same order, so the same calls give the same
 * commands, the same rects and the same refusals.
 *
 * Beside each command the recorder keeps a note of where it draws:
 *   'how'      CommandReach for a command made elsewhere, null for the recorder's own shapes
 *   'restless' its pixels can change with nothing in it changing
 *   'reach'    [x0, y0, x1, y1] in pixels, or for CONTOURS and POINTS [grow, 0, 0, 0]
 *   'clip'     [x, y, width, height]
 *   'second'   null, or ['reach' => [x0, y0, x1, y1], 'clip' => [x, y, width, height]]
 *   'reads'    null, or ['area' => [x, y, width, height], 'reach' => float]
 */
final class PHPCommandRecorder implements Recorder
{
    private bool $recording = false;

    /** The frame being recorded, or the innermost layer: its commands and their notes. */
    private array $open = [];

    private array $openNotes = [];

    /** The last finished frame, and the one before it. */
    private array $last = [];

    private array $before = [];

    private array $lastNotes = [];

    private array $beforeNotes = [];

    /** The transform, a b c d e f. */
    private array $m = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    private mixed $clipValue = null;

    /** [x, y, width, height] */
    private array $clip = [0, 0, 0, 0];

    /** What push() keeps; entries below $base belong to what is around the open layer. */
    private array $stack = [];

    private int $base = 0;

    /** What enter() sets aside. */
    private array $layers = [];

    public function begin(int $width, int $height, mixed $clip): void
    {
        if ($this->recording) {
            throw new Error('A frame is already open: finish() or abandon() it before begin()');
        }
        if ($width < 1 || $width > 16777216) {
            throw new ValueError('PHPCommandRecorder::begin(): Argument #1 ($width) must be between 1 and 16777216');
        }
        if ($height < 1 || $height > 16777216) {
            throw new ValueError('PHPCommandRecorder::begin(): Argument #2 ($height) must be between 1 and 16777216');
        }
        $this->open = [];
        $this->openNotes = [];
        $this->m = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $this->clipValue = $clip;
        $this->clip = [0, 0, $width, $height];
        $this->recording = true;
    }

    public function recording(): bool
    {
        return $this->recording;
    }

    public function abandon(): void
    {
        $this->close();
    }

    public function finish(): array
    {
        if (! $this->recording) {
            throw $this->noFrame();
        }
        // A layer still entered is not part of the frame: its commands are dropped with it.
        while ($this->layers !== []) {
            $layer = array_pop($this->layers);
            $this->open = $layer['commands'];
            $this->openNotes = $layer['notes'];
        }
        $frame = $this->open;
        $notes = $this->openNotes;
        $this->close();

        $this->before = $this->last;
        $this->beforeNotes = $this->lastNotes;
        $this->last = $frame;
        $this->lastNotes = $notes;

        return $frame;
    }

    private function close(): void
    {
        $this->open = [];
        $this->openNotes = [];
        $this->layers = [];
        $this->stack = [];
        $this->base = 0;
        $this->clipValue = null;
        $this->recording = false;
    }

    private function noFrame(): Error
    {
        return new Error('No frame is open: call begin() first');
    }

    public function push(): void
    {
        if (! $this->recording) {
            throw $this->noFrame();
        }
        $this->stack[] = ['m' => $this->m, 'clipValue' => $this->clipValue, 'clip' => $this->clip];
    }

    public function pop(): void
    {
        if (! $this->recording || count($this->stack) === $this->base) {
            throw new Error('pop() without a push()');
        }
        $saved = array_pop($this->stack);
        $this->m = $saved['m'];
        $this->clipValue = $saved['clipValue'];
        $this->clip = $saved['clip'];
    }

    public function depth(): int
    {
        return count($this->stack) - $this->base;
    }

    public function concat(float $a, float $b, float $c, float $d, float $e, float $f): void
    {
        if (! $this->recording) {
            throw $this->noFrame();
        }
        [$ma, $mb, $mc, $md, $me, $mf] = $this->m;
        $next = [
            $ma * $a + $mc * $b,
            $mb * $a + $md * $b,
            $ma * $c + $mc * $d,
            $mb * $c + $md * $d,
            $ma * $e + $mc * $f + $me,
            $mb * $e + $md * $f + $mf,
        ];
        foreach ($next as $component) {
            if (! is_finite($component)) {
                throw new CommandRecorderException(RecordingError::BAD_MATRIX, '', $component);
            }
        }
        $this->m = $next;
    }

    public function matrix(): array
    {
        if (! $this->recording) {
            throw $this->noFrame();
        }

        return $this->m;
    }

    public function clip(mixed $clip, int $x, int $y, int $width, int $height): void
    {
        if (! $this->recording) {
            throw $this->noFrame();
        }
        $this->checkRect('clip', $x, $y, $width, $height);
        $this->clipValue = $clip;
        $this->clip = [$x, $y, $width, $height];
    }

    public function clipValue(): mixed
    {
        if (! $this->recording) {
            throw $this->noFrame();
        }

        return $this->clipValue;
    }

    public function enter(mixed $clip, int $x, int $y, int $width, int $height): void
    {
        if (! $this->recording) {
            throw $this->noFrame();
        }
        $this->checkRect('enter', $x, $y, $width, $height);
        $this->layers[] = [
            'commands' => $this->open,
            'notes' => $this->openNotes,
            'clipValue' => $this->clipValue,
            'clip' => $this->clip,
            'base' => $this->base,
        ];
        $this->open = [];
        $this->openNotes = [];
        $this->clipValue = $clip;
        $this->clip = [$x, $y, $width, $height];
        $this->base = count($this->stack);
    }

    public function leave(): array
    {
        if (! $this->recording || $this->layers === []) {
            throw new Error('leave() without an enter()');
        }
        $commands = $this->open;

        // the pushes the layer left behind go with it
        array_splice($this->stack, $this->base);
        $layer = array_pop($this->layers);
        $this->open = $layer['commands'];
        $this->openNotes = $layer['notes'];
        $this->clipValue = $layer['clipValue'];
        $this->clip = $layer['clip'];
        $this->base = $layer['base'];

        return $commands;
    }

    /** Every edge of a rect within 16777216 either way. */
    private function checkRect(string $call, int $x, int $y, int $width, int $height): void
    {
        foreach (['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height] as $name => $value) {
            if ($value < -16777216 || $value > 16777216) {
                throw new ValueError("PHPCommandRecorder::{$call}(): Argument (\${$name}) must be between -16777216 and 16777216");
            }
        }
    }

    private function needsFrame(string $call): void
    {
        if (! $this->recording) {
            throw new CommandRecorderException(RecordingError::NOT_RECORDING, $call, 0.0);
        }
    }

    /** Each named argument is a finite number, checked in the order given. */
    private function finite(array $named): void
    {
        foreach ($named as $name => $value) {
            if (! is_finite($value)) {
                throw new CommandRecorderException(RecordingError::NOT_FINITE, $name, 0.0);
            }
        }
    }

    /**
     * (x, y) through the transform; each coordinate is held to 16777216, x first.
     *
     * @return array{float, float}
     */
    private function point(float $x, float $y, string $xName, string $yName): array
    {
        [$a, $b, $c, $d, $e, $f] = $this->m;
        $tx = $a * $x + $c * $y + $e;
        $ty = $b * $x + $d * $y + $f;

        if (! is_finite($tx) || abs($tx) > 16777216.0) {
            throw new CommandRecorderException(RecordingError::PAST_LIMIT, $xName, $tx);
        }
        if (! is_finite($ty) || abs($ty) > 16777216.0) {
            throw new CommandRecorderException(RecordingError::PAST_LIMIT, $yName, $ty);
        }

        return [$tx, $ty];
    }

    /** A rect's corners through the transform: top-left, top-right, bottom-right, bottom-left. */
    private function corners(float $x, float $y, float $w, float $h): array
    {
        return [
            $this->point($x, $y, 'x', 'y'),
            $this->point($x + $w, $y, 'x', 'y'),
            $this->point($x + $w, $y + $h, 'x', 'y'),
            $this->point($x, $y + $h, 'x', 'y'),
        ];
    }

    /** A stroke as wide as the transform makes it: scaled by the square root of the area it scales by. */
    private function strokeWidth(float $stroke): float
    {
        [$a, $b, $c, $d] = $this->m;
        $scaled = $stroke * sqrt(abs($a * $d - $b * $c));

        if (abs($scaled) > 16777216.0) {
            throw new CommandRecorderException(RecordingError::PAST_LIMIT, 'stroke', $scaled);
        }

        return $scaled;
    }

    /** No turn and no shear. */
    private function aligned(): bool
    {
        return $this->m[1] == 0.0 && $this->m[2] == 0.0;
    }

    /** Upright, x and y scaled alike, by more than 0. */
    private function even(): bool
    {
        return $this->m[1] == 0.0 && $this->m[2] == 0.0 && $this->m[0] == $this->m[3] && $this->m[0] > 0.0;
    }

    /** The longest a unit length becomes under the transform. */
    private function stretch(): float
    {
        [$a, $b, $c, $d] = $this->m;
        $along = $a * $a + $b * $b;
        $across = $c * $c + $d * $d;

        return sqrt($along < $across ? $across : $along);
    }

    /** How many straight sides keep a circle of this radius, in pixels, within 0.1 of a pixel: 12..1024. */
    private function sides(float $radius): int
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

    /** An ellipse as a polygon through the transform. */
    private function flatten(float $cx, float $cy, float $rx, float $ry): array
    {
        $sides = $this->sides(($rx < $ry ? $ry : $rx) * $this->stretch());
        $points = [];

        for ($i = 0; $i < $sides; $i++) {
            $angle = 2 * M_PI * (float) $i / (float) $sides;
            $points[] = $this->point($cx + $rx * cos($angle), $cy + $ry * sin($angle), 'cx', 'cy');
        }

        return $points;
    }

    /** A rounded rect's outline through the transform, clockwise from the top-left corner. */
    private function outline(float $x, float $y, float $w, float $h, array $radii): array
    {
        $stretch = $this->stretch();
        $corners = [
            [$x + $radii[0], $y + $radii[0], $radii[0], M_PI],
            [$x + $w - $radii[1], $y + $radii[1], $radii[1], 1.5 * M_PI],
            [$x + $w - $radii[2], $y + $h - $radii[2], $radii[2], 0.0],
            [$x + $radii[3], $y + $h - $radii[3], $radii[3], 0.5 * M_PI],
        ];
        $points = [];

        foreach ($corners as [$cx, $cy, $r, $from]) {
            if ($r <= 0) {
                $points[] = $this->point($cx, $cy, 'x', 'y');

                continue;
            }
            $steps = intdiv($this->sides($r * $stretch) + 3, 4);
            for ($i = 0; $i <= $steps; $i++) {
                $angle = $from + 0.5 * M_PI * (float) $i / (float) $steps;
                $points[] = $this->point($cx + $r * cos($angle), $cy + $r * sin($angle), 'x', 'y');
            }
        }

        return $points;
    }

    private function clipEmpty(): bool
    {
        return $this->clip[2] <= 0 || $this->clip[3] <= 0;
    }

    /** The smallest and largest x and y of points, joined into $reach when it is given. */
    private function reach(array $points, ?array $reach = null): array
    {
        foreach ($points as [$x, $y]) {
            if ($reach === null) {
                $reach = [$x, $y, $x, $y];

                continue;
            }
            if ($x < $reach[0]) {
                $reach[0] = $x;
            }
            if ($x > $reach[2]) {
                $reach[2] = $x;
            }
            if ($y < $reach[1]) {
                $reach[1] = $y;
            }
            if ($y > $reach[3]) {
                $reach[3] = $y;
            }
        }

        return $reach;
    }

    /** One of the recorder's own commands, last in the open frame, with where it reaches, inside the current clip. */
    private function add(array $command, float $x0, float $y0, float $x1, float $y1): void
    {
        $this->open[] = $command;
        $this->openNotes[] = [
            'how' => null,
            'restless' => false,
            'reach' => [$x0, $y0, $x1, $y1],
            'clip' => $this->clip,
            'second' => null,
            'reads' => null,
        ];
    }

    /** An int colour, or a gradient [kind, geometry, stops, spread]: a list of four. As ext-comrec's phppq_paint_ok(). */
    private function paintOk(int|array $paint, string $call, int $arg, string $name): void
    {
        if (is_array($paint) && (! array_is_list($paint) || count($paint) !== 4)) {
            throw new ValueError("{$call}(): Argument #{$arg} (\${$name}) must be an int colour or a gradient [kind, geometry, stops, spread]");
        }
    }

    /** A paint into a command: an int as it is; a gradient as given with the transform appended, [a, b, c, d, e, f]. */
    private function painted(int|array $paint): int|array
    {
        if (is_int($paint)) {
            return $paint;
        }
        $paint[] = $this->m;

        return $paint;
    }

    /** ['path', contours, rule, rgba, clip] of one or two contours; nothing under an empty clip. */
    private function recordPath(array $first, ?array $second, FillRule $rule, int|array $rgba): void
    {
        if ($this->clipEmpty()) {
            return;
        }
        $contours = [$first];
        $reach = $this->reach($first);
        if ($second !== null) {
            $contours[] = $second;
            $reach = $this->reach($second, $reach);
        }
        $this->add(['path', $contours, $rule, $this->painted($rgba), $this->clipValue], $reach[0] - 1.0, $reach[1] - 1.0, $reach[2] + 1.0, $reach[3] + 1.0);
    }

    /** ['polyline', points, stroke, closed, rgba, clip] with the stroke scaled. */
    private function recordStroked(array $points, float $stroke, bool $closed, int|array $rgba): void
    {
        $stroke = $this->strokeWidth($stroke);
        if (! ($stroke > 0) || $this->clipEmpty()) {
            return;
        }
        $reach = $this->reach($points);
        $grow = 2.0 * $stroke + 1.0;
        $this->add(['polyline', $points, $stroke, $closed, $this->painted($rgba), $this->clipValue], $reach[0] - $grow, $reach[1] - $grow, $reach[2] + $grow, $reach[3] + $grow);
    }

    /** [kind, x0, y0, x1, y1, [tl, tr, br, bl], (inset,) rgba, clip]: a 'roundrect', or a 'roundring' when inset is given. */
    private function recordRound(string $kind, float $x0, float $y0, float $x1, float $y1, array $radii, float $scale, ?float $inset, int|array $rgba): void
    {
        $command = [$kind, $x0, $y0, $x1, $y1, [$radii[0] * $scale, $radii[1] * $scale, $radii[2] * $scale, $radii[3] * $scale]];
        if ($inset !== null) {
            $command[] = $inset;
        }
        $command[] = $this->painted($rgba);
        $command[] = $this->clipValue;
        $this->add($command, $x0 - 1.0, $y0 - 1.0, $x1 + 1.0, $y1 + 1.0);
    }

    public function clear(int $rgba): void
    {
        $this->needsFrame('clear');
        $this->open[] = ['clear', $rgba];
        $this->openNotes[] = [
            'how' => CommandReach::WHOLE,
            'restless' => false,
            'reach' => [0.0, 0.0, 0.0, 0.0],
            'clip' => [0, 0, 0, 0],
            'second' => null,
            'reads' => null,
        ];
    }

    public function fillRect(float $x, float $y, float $width, float $height, int|array $rgba): void
    {
        $this->paintOk($rgba, 'fillRect', 5, 'rgba');
        $this->needsFrame('fillRect');
        $this->finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $this->recordPath($this->corners($x, $y, $width, $height), null, FillRule::NON_ZERO, $rgba);
    }

    public function strokeRect(float $x, float $y, float $width, float $height, int|array $rgba, float $stroke): void
    {
        $this->paintOk($rgba, 'strokeRect', 5, 'rgba');
        $this->needsFrame('strokeRect');
        $this->finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'stroke' => $stroke]);
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $this->recordStroked($this->corners($x, $y, $width, $height), $stroke, true, $rgba);
    }

    public function line(float $x0, float $y0, float $x1, float $y1, int|array $rgba, float $stroke): void
    {
        $this->paintOk($rgba, 'line', 5, 'rgba');
        $this->needsFrame('line');
        $this->finite(['x0' => $x0, 'y0' => $y0, 'x1' => $x1, 'y1' => $y1, 'stroke' => $stroke]);
        $this->recordStroked([$this->point($x0, $y0, 'x0', 'y0'), $this->point($x1, $y1, 'x1', 'y1')], $stroke, false, $rgba);
    }

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, int|array $rgba): void
    {
        $this->paintOk($rgba, 'fillTriangle', 7, 'rgba');
        $this->needsFrame('fillTriangle');
        $this->finite(['x0' => $x0, 'y0' => $y0, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2]);
        $points = [$this->point($x0, $y0, 'x0', 'y0'), $this->point($x1, $y1, 'x1', 'y1'), $this->point($x2, $y2, 'x2', 'y2')];
        $this->recordPath($points, null, FillRule::NON_ZERO, $rgba);
    }

    public function fillPath(array $ops, FillRule $rule, int|array $rgba): void
    {
        $this->paintOk($rgba, 'fillPath', 3, 'rgba');
        $this->needsFrame('fillPath');
        $contours = [];
        $reach = null;
        foreach ($this->flattenOps($ops, 'fillPath') as [$points]) {
            $contours[] = $points;
            $reach = $this->reach($points, $reach);
        }
        if ($reach === null || $this->clipEmpty()) {
            return;
        }
        $this->add(['path', $contours, $rule, $this->painted($rgba), $this->clipValue], $reach[0] - 1.0, $reach[1] - 1.0, $reach[2] + 1.0, $reach[3] + 1.0);
    }

    public function strokePath(array $ops, int|array $rgba, float $stroke): void
    {
        $this->paintOk($rgba, 'strokePath', 2, 'rgba');
        $this->needsFrame('strokePath');
        $subpaths = $this->flattenOps($ops, 'strokePath');
        $this->finite(['stroke' => $stroke]);
        foreach ($subpaths as [$points, $closed]) {
            if (count($points) >= 2) {
                $this->recordStroked($points, $stroke, $closed, $rgba);
            }
        }
    }

    /**
     * The ops ['M', x, y], ['L', x, y], ['Q', cx, cy, x, y], ['C', c1x, c1y, c2x, c2y, x, y] and ['Z'] through the
     * transform and flattened, as ext-comrec's phppq_flatten_ops(): the same checks in the same order, the same piece
     * counts, the same sums in the same order.
     *
     * @return list<array{list<array{float, float}>, bool}> each subpath's points and whether a Z closed it
     */
    private function flattenOps(array $ops, string $call): array
    {
        $form = "{$call}(): Argument #1 (\$ops) must be a list of path ops";
        if (! array_is_list($ops)) {
            throw new ValueError($form);
        }
        $subpaths = [];
        $at = $start = [0.0, 0.0];
        $open = $closed = false;
        foreach ($ops as $op) {
            if (! is_array($op) || ! array_is_list($op) || ! isset($op[0]) || ! is_string($op[0]) || strlen($op[0]) !== 1) {
                throw new ValueError($form);
            }
            $count = match ($op[0]) { 'M', 'L' => 2, 'Q' => 4, 'C' => 6, 'Z' => 0, default => 99 };
            if ($count === 99 || count($op) !== $count + 1) {
                throw new ValueError($form);
            }
            for ($k = 1; $k <= $count; $k++) {
                if (! is_int($op[$k]) && ! is_float($op[$k])) {
                    throw new ValueError($form);
                }
            }
            $p = [];
            for ($k = 1; $k <= $count; $k += 2) {
                $this->finite(['x' => $op[$k], 'y' => $op[$k + 1]]);
                array_push($p, ...$this->point($op[$k], $op[$k + 1], 'x', 'y'));
            }
            $c = $op[0];
            if ($c === 'M') {
                $subpaths[] = [[[$p[0], $p[1]]], false];
                $at = $start = [$p[0], $p[1]];
                $open = true;
                $closed = false;
                continue;
            }
            if ($c === 'Z') {
                if ($open && ! $closed) {
                    $subpaths[count($subpaths) - 1][1] = true;
                    $at = $start;
                    $closed = true;
                }
                continue;
            }
            if (! $open) {
                throw new ValueError("{$call}(): Argument #1 (\$ops) must start with an 'M' op");
            }
            if ($closed) {
                $subpaths[] = [[$start], false];
                $closed = false;
            }
            $last = count($subpaths) - 1;
            if ($c === 'L') {
                $subpaths[$last][0][] = [$p[0], $p[1]];
                $at = [$p[0], $p[1]];
            } elseif ($c === 'Q') {
                $pieces = self::pieces(self::length($at[0] - 2.0 * $p[0] + $p[2], $at[1] - 2.0 * $p[1] + $p[3]), 1.0);
                for ($i = 1; $i < $pieces; $i++) {
                    $t = $i / $pieces;
                    $mt = 1.0 - $t;
                    $a = $mt * $mt;
                    $b = 2.0 * $mt * $t;
                    $d = $t * $t;
                    $subpaths[$last][0][] = [($a * $at[0] + $b * $p[0]) + $d * $p[2], ($a * $at[1] + $b * $p[1]) + $d * $p[3]];
                }
                $subpaths[$last][0][] = [$p[2], $p[3]];
                $at = [$p[2], $p[3]];
            } else {
                $d1 = self::length($at[0] - 2.0 * $p[0] + $p[2], $at[1] - 2.0 * $p[1] + $p[3]);
                $d2 = self::length($p[0] - 2.0 * $p[2] + $p[4], $p[1] - 2.0 * $p[3] + $p[5]);
                $pieces = self::pieces($d1 > $d2 ? $d1 : $d2, 3.0);
                for ($i = 1; $i < $pieces; $i++) {
                    $t = $i / $pieces;
                    $mt = 1.0 - $t;
                    $a = $mt * $mt * $mt;
                    $b = 3.0 * $mt * $mt * $t;
                    $e = 3.0 * $mt * $t * $t;
                    $d = $t * $t * $t;
                    $subpaths[$last][0][] = [(($a * $at[0] + $b * $p[0]) + $e * $p[2]) + $d * $p[4], (($a * $at[1] + $b * $p[1]) + $e * $p[3]) + $d * $p[5]];
                }
                $subpaths[$last][0][] = [$p[4], $p[5]];
                $at = [$p[4], $p[5]];
            }
        }

        return $subpaths;
    }

    /** ceil(sqrt(dd * k / (4 x 0.25))), 1 to 1024: how many straight pieces a curve becomes. */
    private static function pieces(float $dd, float $k): int
    {
        $n = ceil(sqrt($dd * $k / (4.0 * 0.25)));
        if (! ($n >= 1.0)) {
            return 1;
        }

        return $n > 1024.0 ? 1024 : (int) $n;
    }

    private static function length(float $x, float $y): float
    {
        return sqrt($x * $x + $y * $y);
    }

    /** An ellipse under a transform that keeps its axes: its centre and radii in pixels. */
    private function upright(float $cx, float $cy, float $rx, float $ry): array
    {
        [$x, $y] = $this->point($cx, $cy, 'cx', 'cy');
        $rx *= abs($this->m[0]);
        $ry *= abs($this->m[3]);
        if ($rx > 16777216.0) {
            throw new CommandRecorderException(RecordingError::PAST_LIMIT, 'rx', $rx);
        }
        if ($ry > 16777216.0) {
            throw new CommandRecorderException(RecordingError::PAST_LIMIT, 'ry', $ry);
        }

        return [$x, $y, $rx, $ry];
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, int|array $rgba): void
    {
        $this->paintOk($rgba, 'fillEllipse', 5, 'rgba');
        $this->needsFrame('fillEllipse');
        $this->finite(['cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry]);
        if ($rx <= 0 || $ry <= 0) {
            return;
        }
        if (! $this->aligned()) {
            $this->recordPath($this->flatten($cx, $cy, $rx, $ry), null, FillRule::NON_ZERO, $rgba);

            return;
        }
        [$x, $y, $rx, $ry] = $this->upright($cx, $cy, $rx, $ry);
        if (! ($rx > 0) || ! ($ry > 0) || $this->clipEmpty()) {
            return;
        }
        $this->add(['ellipse', $x, $y, $rx, $ry, $this->painted($rgba), $this->clipValue], $x - $rx - 1.0, $y - $ry - 1.0, $x + $rx + 1.0, $y + $ry + 1.0);
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, int|array $rgba, float $stroke): void
    {
        $this->paintOk($rgba, 'strokeEllipse', 5, 'rgba');
        $this->needsFrame('strokeEllipse');
        $this->finite(['cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry, 'stroke' => $stroke]);
        if ($rx <= 0 || $ry <= 0) {
            return;
        }
        if (! $this->aligned()) {
            $this->recordStroked($this->flatten($cx, $cy, $rx, $ry), $stroke, true, $rgba);

            return;
        }
        [$x, $y, $rx, $ry] = $this->upright($cx, $cy, $rx, $ry);
        $stroke = $this->strokeWidth($stroke);
        if (! ($rx > 0) || ! ($ry > 0) || ! ($stroke > 0) || $this->clipEmpty()) {
            return;
        }
        $half = $stroke / 2;
        $this->add(
            ['ring', $x, $y, $rx, $ry, $stroke, $this->painted($rgba), $this->clipValue],
            $x - $rx - $half - 1.0, $y - $ry - $half - 1.0, $x + $rx + $half + 1.0, $y + $ry + $half + 1.0,
        );
    }

    /** Four radii, each held to 0..min(width, height) / 2. */
    private function radii(array $radii, float $width, float $height): array
    {
        $half = ($height < $width ? $height : $width) / 2;
        if (! array_is_list($radii) || count($radii) !== 4) {
            throw new CommandRecorderException(RecordingError::BAD_RADII, '', 0.0);
        }
        $held = [];
        foreach ($radii as $i => $value) {
            if (! is_int($value) && ! is_float($value)) {
                throw new CommandRecorderException(RecordingError::BAD_RADII, '', 0.0);
            }
            $r = (float) $value;
            $this->finite(["radius {$i}" => $r]);
            if ($half < $r) {
                $r = $half;
            }
            $held[] = $r > 0.0 ? $r : 0.0;
        }

        return $held;
    }

    public function roundRect(float $x, float $y, float $width, float $height, array $radii, int|array $fill, int|array|null $border, float $borderWidth): void
    {
        $this->paintOk($fill, 'roundRect', 6, 'fill');
        $border === null || $this->paintOk($border, 'roundRect', 7, 'border');
        $this->needsFrame('roundRect');
        $this->finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'borderWidth' => $borderWidth]);
        $radii = $this->radii($radii, $width, $height);
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $bw = $border === null ? 0.0 : ($borderWidth > 0.0 ? $borderWidth : 0.0);
        $inner = [];
        foreach ($radii as $r) {
            $r -= $bw;
            $inner[] = $r > 0.0 ? $r : 0.0;
        }
        $hollow = $width > 2 * $bw && $height > 2 * $bw;

        if (! $this->even()) {
            // A turned rounded rect is a path of straight sides, as a turned ellipse is.
            $outer = $this->outline($x, $y, $width, $height, $radii);
            if ($bw <= 0) {
                $this->recordPath($outer, null, FillRule::NON_ZERO, $fill);

                return;
            }
            $hole = null;
            if ($hollow) {
                $hole = $this->outline($x + $bw, $y + $bw, $width - 2 * $bw, $height - 2 * $bw, $inner);
                $this->recordPath($hole, null, FillRule::NON_ZERO, $fill);
            }
            $this->recordPath($outer, $hole, FillRule::EVEN_ODD, $border);

            return;
        }

        $scale = $this->m[0];
        [$x0, $y0] = $this->point($x, $y, 'x', 'y');
        [$x1, $y1] = $this->point($x + $width, $y + $height, 'x', 'y');
        if ($this->clipEmpty()) {
            return;
        }
        if ($bw <= 0) {
            $this->recordRound('roundrect', $x0, $y0, $x1, $y1, $radii, $scale, null, $fill);

            return;
        }
        $inset = $bw * $scale;
        if ($hollow) {
            $this->recordRound('roundrect', $x0 + $inset, $y0 + $inset, $x1 - $inset, $y1 - $inset, $inner, $scale, null, $fill);
        }
        $this->recordRound('roundring', $x0, $y0, $x1, $y1, $radii, $scale, $inset, $border);
    }

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, int $rgba, float $dx, float $dy): void
    {
        $this->needsFrame('shadow');
        $this->finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'radius' => $radius, 'blur' => $blur, 'dx' => $dx, 'dy' => $dy]);
        if ($width <= 0 || $height <= 0) {
            return;
        }
        if (! $this->even()) {
            throw new CommandRecorderException(RecordingError::UNEVEN, 'shadow', 0.0);
        }
        $scale = $this->m[0];
        $blur = $blur > 0.0 ? $blur : 0.0;
        [$x0, $y0] = $this->point($x + $dx, $y + $dy, 'x', 'y');
        [$x1, $y1] = $this->point($x + $width + $dx, $y + $height + $dy, 'x', 'y');
        $half = ($height < $width ? $height : $width) / 2;
        if ($half < $radius) {
            $radius = $half;
        }
        $radius = $radius > 0.0 ? $radius : 0.0;
        if ($this->clipEmpty()) {
            return;
        }
        $blur *= $scale;
        $this->add(
            ['shadow', $x0, $y0, $x1, $y1, $radius * $scale, $blur, $rgba, $this->clipValue],
            $x0 - $blur - 1.0, $y0 - $blur - 1.0, $x1 + $blur + 1.0, $y1 + $blur + 1.0,
        );
    }

    public function glyphs(array $runs, float $x, float $y, ?array $ink, int $rgba): void
    {
        if ($ink !== null) {
            $ink = $this->numbers($ink, 4, 'PHPCommandRecorder::glyphs(): Argument #4 ($ink) must be null or [x0, y0, x1, y1], four finite numbers');
        }
        $this->needsFrame('text');
        $this->finite(['x' => $x, 'y' => $y]);
        $origin = $this->point($x, $y, 'x', 'y');
        if ($ink === null || $this->clipEmpty()) {
            return;
        }
        // the ink's box through the transform, grown by a pixel: anti-aliased edges, and each glyph moved to a whole pixel
        $reach = $this->reach([
            $this->point($x + $ink[0], $y + $ink[1], 'x', 'y'),
            $this->point($x + $ink[2], $y + $ink[1], 'x', 'y'),
            $this->point($x + $ink[2], $y + $ink[3], 'x', 'y'),
            $this->point($x + $ink[0], $y + $ink[3], 'x', 'y'),
        ]);
        $box = [$reach[0] - 1.0, $reach[1] - 1.0, $reach[2] + 1.0, $reach[3] + 1.0];
        [$a, $b, $c, $d] = $this->m;
        $this->add(['glyphs', $runs, [$a, $b, $c, $d, $origin[0], $origin[1]], $rgba, $box, $this->clipValue], ...$box);
    }

    public function drawn(int $surfaceWidth, int $surfaceHeight, int $unitWidth, int $unitHeight, int $limitWidth, int $limitHeight, array $also = []): array
    {
        [$grain, $extra] = $this->measure('drawn', $surfaceWidth, $surfaceHeight, $unitWidth, $unitHeight, $limitWidth, $limitHeight, $also);
        $merged = [];
        foreach ($this->last as $i => $command) {
            $box = self::box($command, $this->lastNotes[$i], $surfaceWidth, $surfaceHeight);
            if ($box !== null) {
                $merged = self::mergeOne($merged, $box, $grain);
            }
        }

        return self::flat(self::merge($extra, $merged, $grain));
    }

    public function changed(int $surfaceWidth, int $surfaceHeight, int $unitWidth, int $unitHeight, int $limitWidth, int $limitHeight, array $also = []): array
    {
        [$sw, $sh] = [$surfaceWidth, $surfaceHeight];
        [$grain, $extra] = $this->measure('changed', $sw, $sh, $unitWidth, $unitHeight, $limitWidth, $limitHeight, $also);

        // By position: an equal command changed nothing; any other marks its box and the box of the one it replaced.
        $differing = [];
        $was = count($this->before);
        $is = count($this->last);
        for ($i = 0, $count = max($was, $is); $i < $count; $i++) {
            if ($i < $was && $i < $is && ! $this->lastNotes[$i]['restless'] && $this->before[$i] == $this->last[$i]) {
                continue;
            }
            if ($i < $was && ($box = self::box($this->before[$i], $this->beforeNotes[$i], $sw, $sh)) !== null) {
                $differing = self::mergeOne($differing, $box, $grain);
            }
            if ($i < $is && ($box = self::box($this->last[$i], $this->lastNotes[$i], $sw, $sh)) !== null) {
                $differing = self::mergeOne($differing, $box, $grain);
            }
        }
        $regions = self::merge($extra, self::merge($differing, [], $grain), $grain);

        // What reads under itself, grown by its reach and kept on the surface.
        $outers = [];
        foreach ($this->lastNotes as $note) {
            if ($note['reads'] !== null) {
                [$x, $y, $w, $h] = $note['reads']['area'];
                $by = (int) ceil($note['reads']['reach']);
                $grown = self::intersect([$x - $by, $y - $by, $w + 2 * $by, $h + 2 * $by], [0, 0, $sw, $sh]);
                if ($grown !== null) {
                    $outers[] = $grown;
                }
            }
        }
        // One that overlaps a changed rect is redrawn whole, and may then reach the next.
        do {
            $kept = [];
            $grew = false;
            foreach ($outers as $outer) {
                $touched = false;
                foreach ($regions as $region) {
                    if (self::intersect($region, $outer) !== null) {
                        $touched = true;
                        break;
                    }
                }
                if ($touched) {
                    $regions = self::mergeOne(self::merge($regions, [], $grain), $outer, $grain);
                    $grew = true;
                } else {
                    $kept[] = $outer;
                }
            }
            $outers = $kept;
        } while ($grew);

        return self::flat($regions);
    }

    /** $count numbers of a list, as floats. */
    private function numbers(array $list, int $count, string $refusal): array
    {
        if (! array_is_list($list) || count($list) !== $count) {
            throw new ValueError($refusal);
        }
        $out = [];
        foreach ($list as $value) {
            if (is_int($value)) {
                $out[] = (float) $value;
            } elseif (is_float($value) && is_finite($value)) {
                $out[] = $value;
            } else {
                throw new ValueError($refusal);
            }
        }

        return $out;
    }

    /** Four numbers as a rect whose edges are whole and within 16777216, or null. */
    private function wholeRect(array $n): ?array
    {
        foreach ($n as $value) {
            if ($value != floor($value) || abs($value) > 16777216.0) {
                return null;
            }
        }

        return [(int) $n[0], (int) $n[1], (int) $n[2], (int) $n[3]];
    }

    public function lower(array $command, string $form): ?array
    {
        return Lowering::lower($command, $form);
    }

    public function append(array $command, CommandReach $how, float $grow = 0.0, ?array $pieces = null, bool $restless = false, ?array $reads = null): void
    {
        if (! $this->recording) {
            throw $this->noFrame();
        }
        $note = [
            'how' => $how,
            'restless' => $restless,
            'reach' => [0.0, 0.0, 0.0, 0.0],
            'clip' => $this->clip,
            'second' => null,
            'reads' => null,
        ];

        switch ($how) {
            case CommandReach::WHOLE:
                break;
            case CommandReach::CONTOURS:
            case CommandReach::POINTS:
                if (! is_finite($grow) || $grow < 0.0) {
                    throw new ValueError('PHPCommandRecorder::append(): Argument #3 ($grow) must be a finite number, 0 or more');
                }
                $note['reach'][0] = $grow;
                break;
            case CommandReach::PIECES:
                $count = $pieces === null ? 0 : count($pieces);
                if ($count !== 8 && $count !== 16) {
                    throw new ValueError('PHPCommandRecorder::append(): Argument #4 ($pieces) must be a list of 8 or 16 numbers for CommandReach::PIECES');
                }
                $n = $this->numbers($pieces, $count, 'PHPCommandRecorder::append(): Argument #4 ($pieces) must be a list of 8 or 16 finite numbers');
                $clip = $this->wholeRect(array_slice($n, 4, 4));
                $secondClip = $count === 16 ? $this->wholeRect(array_slice($n, 12, 4)) : [0, 0, 0, 0];
                if ($clip === null || $secondClip === null) {
                    throw new ValueError('PHPCommandRecorder::append(): Argument #4 ($pieces) must give each clip as four whole numbers within the limit');
                }
                $note['reach'] = [$n[0], $n[1], $n[2], $n[3]];
                $note['clip'] = $clip;
                if ($count === 16) {
                    $note['second'] = ['reach' => [$n[8], $n[9], $n[10], $n[11]], 'clip' => $secondClip];
                }
                break;
        }
        if ($reads !== null) {
            $n = $this->numbers($reads, 5, 'PHPCommandRecorder::append(): Argument #6 ($reads) must be [x, y, width, height, reach], five finite numbers');
            $area = $this->wholeRect(array_slice($n, 0, 4));
            if ($area === null || $n[4] < 0.0 || $n[4] > 16777216.0) {
                throw new ValueError('PHPCommandRecorder::append(): Argument #6 ($reads) must give a whole rect within the limit and a reach of 0 or more');
            }
            $note['reads'] = ['area' => $area, 'reach' => $n[4]];
        }
        $this->open[] = $command;
        $this->openNotes[] = $note;
    }

    /** The overlap of two rects [x, y, w, h], or null when they share no pixel. */
    private static function intersect(array $a, array $b): ?array
    {
        $x = $a[0] > $b[0] ? $a[0] : $b[0];
        $y = $a[1] > $b[1] ? $a[1] : $b[1];
        $right = min($a[0] + $a[2], $b[0] + $b[2]);
        $bottom = min($a[1] + $a[3], $b[1] + $b[3]);

        return $right <= $x || $bottom <= $y ? null : [$x, $y, $right - $x, $bottom - $y];
    }

    /** The smallest rect holding both. */
    private static function union(array $a, array $b): array
    {
        $x = $a[0] < $b[0] ? $a[0] : $b[0];
        $y = $a[1] < $b[1] ? $a[1] : $b[1];

        return [$x, $y, max($a[0] + $a[2], $b[0] + $b[2]) - $x, max($a[1] + $a[3], $b[1] + $b[3]) - $y];
    }

    /**
     * Grown outward to whole units, and held to the limit on the right and the bottom.
     *
     * @param array{unitWidth: int, unitHeight: int, limitWidth: int, limitHeight: int} $grain
     */
    private static function snap(array $r, array $grain): array
    {
        $x1 = intdiv($r[0] + $r[2] + $grain['unitWidth'] - 1, $grain['unitWidth']) * $grain['unitWidth'];
        $y1 = intdiv($r[1] + $r[3] + $grain['unitHeight'] - 1, $grain['unitHeight']) * $grain['unitHeight'];
        $x = intdiv($r[0], $grain['unitWidth']) * $grain['unitWidth'];
        $y = intdiv($r[1], $grain['unitHeight']) * $grain['unitHeight'];
        if ($x1 > $grain['limitWidth']) {
            $x1 = $grain['limitWidth'];
        }
        if ($y1 > $grain['limitHeight']) {
            $y1 = $grain['limitHeight'];
        }

        return [$x, $y, $x1 - $x, $y1 - $y];
    }

    /** The whole pixels a reach [x0, y0, x1, y1] covers on a surface, inside its clip; null when that is no pixel. */
    private static function pieceBox(array $reach, array $clip, int $sw, int $sh): ?array
    {
        $left = floor($reach[0]);
        $top = floor($reach[1]);
        $right = ceil($reach[2]);
        $bottom = ceil($reach[3]);

        // A side that is not a number, or a rect that starts past the surface or ends before it, covers nothing.
        if (! ($left < (float) $sw) || ! ($top < (float) $sh) || ! ($right > 0.0) || ! ($bottom > 0.0)) {
            return null;
        }
        $x = $left > 0.0 ? (int) $left : 0;
        $y = $top > 0.0 ? (int) $top : 0;
        $w = ($right < (float) $sw ? (int) $right : $sw) - $x;
        $h = ($bottom < (float) $sh ? (int) $bottom : $sh) - $y;
        if ($w <= 0 || $h <= 0) {
            return null;
        }

        return self::intersect([$x, $y, $w, $h], $clip);
    }

    /** A box put into a merged list: snapped, joined with every rect it overlaps until it overlaps none, put last. */
    private static function mergeOne(array $merged, array $box, array $grain): array
    {
        $box = self::snap($box, $grain);
        do {
            $kept = [];
            $grew = false;
            foreach ($merged as $rect) {
                if (self::intersect($rect, $box) !== null) {
                    $box = self::snap(self::union($box, $rect), $grain);
                    $grew = true;
                } else {
                    $kept[] = $rect;
                }
            }
            $merged = $kept;
        } while ($grew);
        $merged[] = $box;

        return $merged;
    }

    private static function merge(array $in, array $out, array $grain): array
    {
        foreach ($in as $rect) {
            $out = self::mergeOne($out, $rect, $grain);
        }

        return $out;
    }

    /** Join a list of [x, y] into $reach; false when an entry is not a pair of numbers. */
    private static function reachOfPoints(mixed $points, ?array &$reach): bool
    {
        if (! is_array($points)) {
            return false;
        }
        foreach ($points as $point) {
            if (! is_array($point) || ! isset($point[0], $point[1])
                || (! is_int($point[0]) && ! is_float($point[0])) || (! is_int($point[1]) && ! is_float($point[1]))) {
                return false;
            }
            $x = (float) $point[0];
            $y = (float) $point[1];
            if ($reach === null) {
                $reach = [$x, $y, $x, $y];

                continue;
            }
            if ($x < $reach[0]) {
                $reach[0] = $x;
            }
            if ($x > $reach[2]) {
                $reach[2] = $x;
            }
            if ($y < $reach[1]) {
                $reach[1] = $y;
            }
            if ($y > $reach[3]) {
                $reach[3] = $y;
            }
        }

        return true;
    }

    /** The reach of a CONTOURS or POINTS command, read from its points; null when it has none. */
    private static function reachInCommand(array $command, CommandReach $how, float $grow): ?array
    {
        if (! array_key_exists(1, $command)) {
            return null;
        }
        $reach = null;
        if ($how === CommandReach::POINTS) {
            if (! self::reachOfPoints($command[1], $reach)) {
                return null;
            }
        } else {
            if (! is_array($command[1])) {
                return null;
            }
            foreach ($command[1] as $contour) {
                if (! self::reachOfPoints($contour, $reach)) {
                    return null;
                }
            }
        }
        if ($reach === null) {
            return null;
        }

        return [$reach[0] - $grow, $reach[1] - $grow, $reach[2] + $grow, $reach[3] + $grow];
    }

    /** The whole pixels a command draws on a surface; null when it draws none. */
    private static function box(array $command, array $note, int $sw, int $sh): ?array
    {
        $reach = $note['reach'];

        if ($note['how'] === CommandReach::WHOLE) {
            return [0, 0, $sw, $sh];
        }
        if ($note['how'] === CommandReach::CONTOURS || $note['how'] === CommandReach::POINTS) {
            $reach = self::reachInCommand($command, $note['how'], $note['reach'][0]);
            if ($reach === null) {
                return null;
            }
        }
        $box = self::pieceBox($reach, $note['clip'], $sw, $sh);
        if ($note['second'] !== null) {
            $second = self::pieceBox($note['second']['reach'], $note['second']['clip'], $sw, $sh);
            if ($second !== null) {
                $box = $box === null ? $second : self::union($box, $second);
            }
        }

        return $box;
    }

    /**
     * The grain and the extra rects of drawn() and changed().
     *
     * @return array{array{unitWidth: int, unitHeight: int, limitWidth: int, limitHeight: int}, list<array>}
     */
    private function measure(string $call, int $sw, int $sh, int $uw, int $uh, int $lw, int $lh, array $also): array
    {
        foreach ([$sw, $sh, $uw, $uh, $lw, $lh] as $k => $size) {
            if ($size < 1 || $size > 16777216) {
                throw new ValueError("PHPCommandRecorder::{$call}(): Argument #".($k + 1).' must be between 1 and 16777216');
            }
        }
        $extra = [];
        if ($also !== []) {
            if (count($also) % 4 !== 0 || ! array_is_list($also)) {
                throw new ValueError("PHPCommandRecorder::{$call}(): Argument #7 (\$also) must be a list of rects, four integers each");
            }
            foreach ($also as $n) {
                if (! is_int($n) || $n < -16777216 || $n > 16777216) {
                    throw new ValueError("PHPCommandRecorder::{$call}(): Argument #7 (\$also) must be a list of rects, four integers each, within the limit");
                }
            }
            $extra = array_chunk($also, 4);
        }

        return [['unitWidth' => $uw, 'unitHeight' => $uh, 'limitWidth' => $lw, 'limitHeight' => $lh], $extra];
    }

    /** Rects as one flat list: x, y, width, height, four integers a rect. */
    private static function flat(array $rects): array
    {
        $out = [];
        foreach ($rects as $rect) {
            array_push($out, ...$rect);
        }

        return $out;
    }
}
