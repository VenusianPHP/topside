<?php

namespace Surface\Toolkits\Demo\Animations;

use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\Graphics\GEPainter;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Path;

/**
 * Blend2D's Particles demo: particles fly out of the canvas's centre on a curving path, shrinking
 * as they age. All of one colour are one path, filled once: one fill call for up to 5,000 circles.
 */
class Particles extends Animation
{
    private const float MAX_AGE = 650.0;

    private const float RADIUS = 6.0;

    /** Blend2D's eight particle colours, 0xAARRGGBB. */
    private const array COLORS = [0xFF4F00FF, 0xFFFF004F, 0xFFFF7F00, 0xFFFF3F9F, 0xFF7F4FFF, 0xFFFF9F3F, 0xFFFFFF00, 0xFFAF3F00];

    /** @var list<array{float, float, float, float, float, int}> x, y, vx, vy, age, colour */
    private array $particles = [];

    private int $wanted = 500;

    /** Blend2D's "Rotation": how much a particle's heading turns each tick. */
    private int $rotation = 100;

    private bool $colored = false;

    /** The part of a particle owed to the next frame: a tick's spawns, split over the frames it spans. */
    private float $owed = 0.0;

    public function __construct()
    {
        parent::__construct(1234);
    }

    public function title(): string
    {
        return 'Particles';
    }

    public function about(): string
    {
        return 'Particles fly from the centre and shrink as they age. Each colour is one path of circles, filled in one call.';
    }

    public function controls(ViewBuilder $ui, \Closure $changed): void
    {
        $ui->checkbox('colors', 'Colors', $this->colored, function (bool $on) use ($changed): void {
            $this->colored = $on;
            $changed();
        });

        $ui->label('count-label', fn (): string => 'Count: '.$this->wanted);
        $ui->slider('count', 0, 5000, $this->wanted, function (float $value) use ($changed): void {
            $this->wanted = (int) round($value);
            $changed();
        });

        $ui->label('rotation-label', fn (): string => 'Rotation: '.$this->rotation);
        $ui->slider('rotation', 0, 1000, $this->rotation, function (float $value) use ($changed): void {
            $this->rotation = (int) round($value);
            $changed();
        });
    }

    public function count(): int
    {
        return count($this->particles);
    }

    public function step(float $ticks): void
    {
        $turn = $this->rotation * 0.02 / 1000 * $ticks;
        [$cos, $sin] = [cos($turn), sin($turn)];

        $kept = [];
        foreach ($this->particles as [$x, $y, $vx, $vy, $age, $color]) {
            $age += $ticks;
            if ($age >= self::MAX_AGE) {
                continue;
            }
            $kept[] = [$x + $vx * $ticks, $y + $vy * $ticks, $vx * $cos - $vy * $sin, $vx * $sin + $vy * $cos, $age, $color];
        }
        $this->particles = $kept;

        // as Blend2D: about a sixtieth of the count each tick, so the set fills over a second or so
        $this->owed += ($this->next() * $this->wanted / 60 + 0.95) * $ticks;
        for (; $this->owed >= 1.0; $this->owed -= 1.0) {
            if (count($this->particles) >= $this->wanted) {
                $this->owed = 0.0;
                break;
            }
            $angle = $this->next() * M_PI * 2.0;
            $speed = max($this->next() * 2.0, 0.05);
            $this->particles[] = [0.0, 0.0, cos($angle) * $speed, sin($angle) * $speed, min($this->next(), 0.5) * self::MAX_AGE, (int) ($this->next() * count(self::COLORS))];
        }
    }

    public function draw(GEPainter $g): void
    {
        $g->clear(Color::rgba(0, 0, 0, 1.0));
        [$cx, $cy] = [$this->width / 2, $this->height / 2];

        $paths = $this->colored ? array_map(fn () => new Path, self::COLORS) : [new Path];
        foreach ($this->particles as [$x, $y, , , $age, $color]) {
            $r = (self::MAX_AGE - $age) / self::MAX_AGE * self::RADIUS;
            self::circle($paths[$this->colored ? $color : 0], $cx + $x, $cy + $y, $r);
        }

        foreach ($paths as $i => $path) {
            $path->isEmpty() || $g->fill($path, $this->colored ? self::argb(self::COLORS[$i]) : Color::rgba(255, 255, 255, 1.0));
        }
    }
}
