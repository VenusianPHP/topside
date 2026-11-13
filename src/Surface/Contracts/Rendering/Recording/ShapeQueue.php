<?php

namespace Surface\Contracts\Rendering\Recording;

use Surface\Contracts\Graphics\DrawingException;

/**
 * A queue that also records the plain shapes itself: the open-frame check, the number checks, the
 * points through the transform and the command, in one call. A queue without these is whole;
 * VelvetGE's recorder records the shapes in PHP and appends them.
 *
 * Each method is the Painter call of the same name, with its colours as 0xRRGGBBAA, and records
 * exactly what VelvetGE's recorder would: the same commands, the same numbers, and the same
 * DrawingException for the same input.
 *
 * @throws DrawingException As the Painter call of the same name.
 */
interface ShapeQueue extends Queue
{
    public function clear(int $rgba): void;

    public function fillRect(float $x, float $y, float $width, float $height, int $rgba): void;

    public function strokeRect(float $x, float $y, float $width, float $height, int $rgba, float $stroke): void;

    public function line(float $x0, float $y0, float $x1, float $y1, int $rgba, float $stroke): void;

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, int $rgba): void;

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, int $rgba): void;

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, int $rgba, float $stroke): void;

    /**
     * @param  array<int, mixed>  $radii  Unchecked: [tl, tr, br, bl] as the caller gave them.
     * @param  int|null  $border  Null for no border.
     */
    public function roundRect(float $x, float $y, float $width, float $height, array $radii, int $fill, ?int $border, float $borderWidth): void;

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, int $rgba, float $dx, float $dy): void;
}
