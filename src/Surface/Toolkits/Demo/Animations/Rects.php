<?php

namespace Surface\Toolkits\Demo\Animations;

use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\Contracts\Graphics\DrawingContext;
use Surface\NutsAndBolts\Color;

/**
 * Blend2D's Rects demo: up to 20,000 squares of random colour and opacity, each drifting and
 * bouncing off the canvas's edges, drawn five different ways.
 */
class Rects extends Animation
{
    private const array SHAPES = ['RectA', 'RectU', 'RectPath', 'RoundRect', 'Polygon'];

    /** @var list<array{float, float}> centres */
    private array $coords = [];

    /** @var list<array{float, float}> how far each centre moves a tick */
    private array $steps = [];

    /** @var list<Color> */
    private array $colors = [];

    private int $shape = 0;

    private float $size = 64.0;

    public function __construct()
    {
        parent::__construct(0x123456789ABCDEF);
        $this->setCount(200);
    }

    public function title(): string
    {
        return 'Rects';
    }

    public function about(): string
    {
        return 'Squares of random colour bounce off the edges. RectA snaps them to whole points; RectU does not; RectPath, RoundRect and Polygon fill each one as a path.';
    }

    public function controls(ViewBuilder $ui, \Closure $changed): void
    {
        $row = $ui->row('shape', 8);
        $row->label('label', 'Shape:');
        $row->dropdown('menu', self::SHAPES, $this->shape, function (int $index) use ($changed): void {
            $this->shape = $index;
            $changed();
        });

        $ui->label('count-label', fn (): string => 'Count: '.count($this->coords));
        $ui->slider('count', 1, 20000, count($this->coords), function (float $value) use ($changed): void {
            $this->setCount((int) round($value));
            $changed();
        });

        $ui->label('size-label', fn (): string => 'Size: '.(int) $this->size);
        $ui->slider('size', 1, 128, $this->size, function (float $value) use ($changed): void {
            $this->size = round($value);
            $changed();
        });
    }

    public function count(): int
    {
        return count($this->coords);
    }

    /** Grows or shrinks the set: new squares start anywhere on the canvas, going any way. */
    private function setCount(int $count): void
    {
        $this->coords = array_slice($this->coords, 0, $count);
        $this->steps = array_slice($this->steps, 0, $count);
        $this->colors = array_slice($this->colors, 0, $count);

        for ($i = count($this->coords); $i < $count; $i++) {
            $this->coords[] = [$this->next() * $this->width, $this->next() * $this->height];
            $this->steps[] = [($this->next() * 0.5 + 0.04) * $this->sign(), ($this->next() * 0.5 + 0.04) * $this->sign()];
            $this->colors[] = self::argb($this->random->getInt(0, 0xFFFFFFFF));
        }
    }

    public function step(float $ticks): void
    {
        [$w, $h] = [$this->width, $this->height];

        foreach ($this->coords as $i => [$x, $y]) {
            [$sx, $sy] = $this->steps[$i];
            $x += $sx * $ticks;
            $y += $sy * $ticks;

            // as Blend2D: a centre past an edge turns back, and is kept inside it
            if ($x <= 0.0 || $x >= $w) {
                $sx = -$sx;
                $x = min($x + $sx * $ticks, $w);
            }
            if ($y <= 0.0 || $y >= $h) {
                $sy = -$sy;
                $y = min($y + $sy * $ticks, $h);
            }
            $this->coords[$i] = [$x, $y];
            $this->steps[$i] = [$sx, $sy];
        }
    }

    public function draw(DrawingContext $g): void
    {
        $g->clear(Color::rgba(0, 0, 0, 1.0));

        $size = $this->size;
        $half = $size / 2;

        foreach ($this->coords as $i => [$cx, $cy]) {
            [$x, $y] = [$cx - $half, $cy - $half];
            $color = $this->colors[$i];

            match ($this->shape) {
                0 => $g->fillRect((int) $x, (int) $y, (int) $size, (int) $size, $color),
                1 => $g->fillRect($x, $y, $size, $size, $color),
                2 => $g->fill(self::rect($x, $y, $size, $size), $color),
                3 => $g->roundRect($x, $y, $size, $size, [10, 10, 10, 10], $color),
                4 => $g->fillPolygon([
                    [$x + $size / 2, $y],
                    [$x + $size, $y + $size / 3],
                    [$x + $size - $size / 3, $y + $size],
                    [$x + $size / 3, $y + $size],
                    [$x, $y + $size / 3],
                ], $color),
            };
        }
    }
}
