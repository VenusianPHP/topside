<?php

namespace Surface\Toolkits\Demo\Animations;

use Surface\Contracts\Graphics\Canvas;
use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\RadialGradient;

/**
 * Blend2D's Bubbles demo: soft bubbles rise from the bottom edge, swaying side to side, each a
 * radial gradient from a random colour out to nothing.
 */
class Bubbles extends Animation
{
    /**
     * @var list<array{x: float, y: float, r: float, home: float, rise: float, sway: float, speed: float, gradient: RadialGradient}>
     *      home is where it sways about; sway runs 0..2 round a sine; speed is how fast sway moves
     */
    private array $bubbles = [];

    private int $wanted = 100;

    /** Blend2D's "Param": how fast and how widely the bubbles sway. */
    private int $param = 100;

    public function __construct()
    {
        parent::__construct(0x123456789ABCDEF);
    }

    public function title(): string
    {
        return 'Bubbles';
    }

    public function about(): string
    {
        return 'Bubbles rise and sway, each a radial gradient filling its square. Param sets how fast they sway.';
    }

    public function controls(ViewBuilder $ui, \Closure $changed): void
    {
        $ui->label('count-label', fn (): string => 'Count: '.$this->wanted);
        $ui->slider('count', 1, 5000, $this->wanted, function (float $value) use ($changed): void {
            $this->wanted = (int) round($value);
            $changed();
        });

        $ui->label('param-label', fn (): string => 'Param: '.$this->param);
        $ui->slider('param', 0, 1000, $this->param, function (float $value) use ($changed): void {
            $this->param = (int) round($value);
            $changed();
        });
    }

    public function count(): int
    {
        return count($this->bubbles);
    }

    /** A bubble just under the bottom edge. Its gradient is made once, centred on 0,0: draw() moves it there. */
    private function bubble(): array
    {
        $r = $this->next() * 20.0 + 5.0;
        $x = $this->next() * $this->width;
        $param = $this->param * 0.0001;
        $color = self::argb($this->random->getInt(0, 0xFFFFFFFF) | 0x55000000);

        return [
            'x' => $x,
            'y' => $this->height + $r,
            'r' => $r,
            'home' => $x,
            'rise' => $this->next() * 3.0 + 1.0,
            'sway' => 0.0,
            'speed' => $this->next() * ($param * 0.4 + 0.001) + $param * 0.02,
            'gradient' => new RadialGradient(0.0, 0.0, $r, [[0.0, $color], [1.0, Color::rgba(0, 0, 0, 0.0)]]),
        ];
    }

    public function step(float $ticks): void
    {
        if (count($this->bubbles) > $this->wanted) {
            $this->bubbles = array_slice($this->bubbles, 0, $this->wanted);
        }
        // as Blend2D: at most 10 new bubbles a frame, so they come in a stream, not all at once
        for ($added = 0; count($this->bubbles) < $this->wanted && $added < 10; $added++) {
            $this->bubbles[] = $this->bubble();
        }

        foreach ($this->bubbles as $i => $b) {
            $b['sway'] = fmod($b['sway'] + $b['speed'] * $ticks, 2.0);
            $b['x'] = $b['home'] + sin($b['sway'] * M_PI) * 20.0;
            $b['y'] -= $b['rise'] * $ticks;
            $this->bubbles[$i] = $b['y'] < -$b['r'] ? $this->bubble() : $b;
        }
    }

    public function draw(Canvas $g): void
    {
        $g->clear(Color::rgba(0, 0, 0, 1.0));

        foreach ($this->bubbles as $b) {
            $r = $b['r'];
            // the square round the bubble, moved to it: the gradient moves with the painter's transform
            $g->push();
            $g->translate($b['x'], $b['y']);
            $g->fillRect(-$r - 1.0, -$r - 1.0, $r * 2.0 + 2.0, $r * 2.0 + 2.0, $b['gradient']);
            $g->pop();
        }
    }
}
