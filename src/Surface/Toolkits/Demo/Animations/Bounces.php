<?php

namespace Surface\Toolkits\Demo\Animations;

use Surface\Contracts\Graphics\Canvas;
use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\LinearGradient;
use Surface\NutsAndBolts\RadialGradient;

/**
 * Blend2D's Bounces demo: a grid of squares filling the canvas, each turning about its centre and
 * swelling into a circle and back, the wave running across the grid one square after another.
 */
class Bounces extends Animation
{
    private const array STYLES = ['Solid Color', 'Linear Gradient', 'Radial Gradient'];

    private const float MARGIN = 7.0;

    private const float SQUARE = 45.0;

    private int $style = 1;

    /** Blend2D's clock: 2 a tick, so one turn of a square (3,000) takes 12.5 seconds. */
    private float $time = 0.0;

    private int $count = 0;

    public function __construct()
    {
        parent::__construct(0);
    }

    public function title(): string
    {
        return 'Bounces';
    }

    public function about(): string
    {
        return 'A grid of squares, each turning and rounding into a circle and back. Every square is drawn under its own transform.';
    }

    public function controls(ViewBuilder $ui, \Closure $changed): void
    {
        $row = $ui->row('menus', 8);
        $row->label('style-label', 'Style:');
        $row->dropdown('style', self::STYLES, $this->style, function (int $index) use ($changed): void {
            $this->style = $index;
            $changed();
        });
    }

    public function count(): int
    {
        return $this->count;
    }

    public function step(float $ticks): void
    {
        $this->time += 2.0 * $ticks;
    }

    public function draw(Canvas $g): void
    {
        $g->clear(Color::rgba(0, 0, 0, 1.0));

        $full = self::SQUARE + self::MARGIN * 2.0;
        $half = $full / 2.0;
        $columns = (int) ceil($this->width / $full);
        $rows = (int) ceil($this->height / $full);
        $this->count = $columns * $rows;
        $orange = self::argb(0xFFFF7F00);

        for ($i = 0; $i < $this->count; $i++) {
            [$x, $y] = [($i % $columns) * $full, intdiv($i, $columns) * $full];

            // each square is 50 behind the one before it on the clock: the wave across the grid
            $pos = fmod($this->time + $i * 50, 3000.0) / 3000.0;
            $bounce = abs($pos * 2 - 1);
            $color = Color::rgba((int) (($bounce * 50 + 50) / 100 * 255), 0, (int) ((1 - $bounce) * 50 / 100 * 255), 1.0);
            $radius = $bounce * 25;

            // turned about its own centre: the square and its gradient are drawn in the cell's own space
            $g->push();
            $g->translate($x + $half, $y + $half);
            $g->rotate($pos * M_PI * 2);
            $g->translate(-$half, -$half);

            match ($this->style) {
                0 => $g->fillRoundRect(self::MARGIN, self::MARGIN, self::SQUARE, self::SQUARE, $radius, $color),
                1 => $g->fillRoundRect(self::MARGIN, self::MARGIN, self::SQUARE, self::SQUARE, $radius,
                    new LinearGradient(0.0, self::MARGIN, 0.0, self::MARGIN + self::SQUARE, [[0.0, $orange], [1.0, $color]])),
                2 => $g->fillRoundRect(self::MARGIN, self::MARGIN, self::SQUARE, self::SQUARE, $radius,
                    new RadialGradient($half, $half, $half, [[0.0, $orange], [1.0, $color]])),
            };
            $g->pop();
        }
    }
}
