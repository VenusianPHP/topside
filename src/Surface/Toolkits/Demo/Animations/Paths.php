<?php

namespace Surface\Toolkits\Demo\Animations;

use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\Contracts\Graphics\DrawingContext;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\LinearGradient;
use Surface\NutsAndBolts\Path;
use Surface\NutsAndBolts\RadialGradient;

/**
 * Blend2D's Paths demo: one shape whose points all drift and bounce, filled or stroked as a
 * polygon, as quadratic curves or as cubic curves, in a solid colour or a gradient. With many
 * points it crosses itself everywhere, filled even-odd.
 */
class Paths extends Animation
{
    private const array OPS = [
        'Fill Poly', 'Stroke Poly [W=1]', 'Stroke Poly [W=3]', 'Stroke Poly [W=5]',
        'Fill Quads', 'Stroke Quads [W=1]', 'Stroke Quads [W=3]', 'Stroke Quads [W=5]',
        'Fill Cubics', 'Stroke Cubics [W=1]', 'Stroke Cubics [W=3]', 'Stroke Cubics [W=5]',
    ];

    private const array STYLES = ['Solid Color', 'Linear Gradient', 'Radial Gradient'];

    /** @var list<array{float, float}> */
    private array $points = [];

    /** @var list<array{float, float}> */
    private array $steps = [];

    private int $op = 0;

    private int $style = 0;

    public function __construct()
    {
        parent::__construct(0x1234);
        $this->setCount(50);
    }

    public function title(): string
    {
        return 'Paths';
    }

    public function about(): string
    {
        return 'One shape whose every point bounces, filled even-odd or stroked, as straight lines, quadratic curves or cubic curves.';
    }

    public function controls(ViewBuilder $ui, \Closure $changed): void
    {
        $row = $ui->row('menus', 8);
        $row->label('op-label', 'Op:');
        $row->dropdown('op', self::OPS, $this->op, function (int $index) use ($changed): void {
            $this->op = $index;
            $changed();
        });
        $row->label('style-label', 'Style:');
        $row->dropdown('style', self::STYLES, $this->style, function (int $index) use ($changed): void {
            $this->style = $index;
            $changed();
        });

        $ui->label('count-label', fn (): string => 'Count: '.count($this->points));
        $ui->slider('count', 4, 2000, count($this->points), function (float $value) use ($changed): void {
            $this->setCount((int) round($value));
            $changed();
        });
    }

    public function count(): int
    {
        return count($this->points);
    }

    private function setCount(int $count): void
    {
        $this->points = array_slice($this->points, 0, $count);
        $this->steps = array_slice($this->steps, 0, $count);

        for ($i = count($this->points); $i < $count; $i++) {
            $this->points[] = [$this->next() * $this->width, $this->next() * $this->height];
            $this->steps[] = [($this->next() * 0.5 + 0.05) * $this->sign(), ($this->next() * 0.5 + 0.05) * $this->sign()];
        }
    }

    public function step(float $ticks): void
    {
        [$w, $h] = [$this->width, $this->height];

        foreach ($this->points as $i => [$x, $y]) {
            [$sx, $sy] = $this->steps[$i];
            $x += $sx * $ticks;
            $y += $sy * $ticks;
            if ($x <= 0.0 || $x >= $w) {
                $sx = -$sx;
                $x = min($x + $sx * $ticks, $w);
            }
            if ($y <= 0.0 || $y >= $h) {
                $sy = -$sy;
                $y = min($y + $sy * $ticks, $h);
            }
            $this->points[$i] = [$x, $y];
            $this->steps[$i] = [$sx, $sy];
        }
    }

    /** Blend2D's three stops, red to purple to blue, across the whole canvas. */
    private function paint(): Color|Gradient
    {
        $stops = [[0.0, self::argb(0xFFFF0000)], [0.5, self::argb(0xFFAF00AF)], [1.0, self::argb(0xFF0000FF)]];
        [$w, $h] = [$this->width, $this->height];

        return match ($this->style) {
            1 => new LinearGradient(0.0, 0.0, $w, $h, $stops),
            2 => new RadialGradient($w / 2, $h / 2, min($w, $h) / 2, $stops),
            default => Color::rgba(255, 255, 255, 1.0),
        };
    }

    public function draw(DrawingContext $g): void
    {
        $g->clear(Color::rgba(0, 0, 0, 1.0));

        $p = $this->points;
        $n = count($p);
        $path = (new Path)->moveTo($p[0][0], $p[0][1]);

        // 0..3 straight lines, 4..7 quadratics, 8..11 cubics; the first of each four fills, the rest stroke
        $kind = intdiv($this->op, 4);
        if ($kind === 0) {
            for ($i = 1; $i < $n; $i++) {
                $path->lineTo($p[$i][0], $p[$i][1]);
            }
            $path->close();
        } elseif ($kind === 1) {
            for ($i = 3; $i <= $n; $i += 2) {
                $path->quadTo($p[$i - 2][0], $p[$i - 2][1], $p[$i - 1][0], $p[$i - 1][1]);
            }
        } else {
            for ($i = 4; $i <= $n; $i += 3) {
                $path->cubicTo($p[$i - 3][0], $p[$i - 3][1], $p[$i - 2][0], $p[$i - 2][1], $p[$i - 1][0], $p[$i - 1][1]);
            }
        }

        $stroke = $this->op % 4;
        $stroke === 0
            ? $g->fill($path, $this->paint(), FillRule::EVEN_ODD)
            : $g->stroke($path, $this->paint(), new StrokeStyle(($stroke - 1) * 2 + 1));
    }
}
