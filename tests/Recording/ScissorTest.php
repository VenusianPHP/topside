<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\VelvetGE\Recording\Boxes;

// A frame cut into many changed regions is drawn region by region. A command whose clip is the
// whole surface but whose shape is in one corner belongs to that corner's region only: drawn into
// every region, a frame of n shapes in n regions is drawn n times over.
it('draws a command only into the regions its shape reaches, not every region its clip covers', function (): void {
    $surface = new Region(0, 0, 100, 100);
    $square = fn (float $x, float $y): array => ['path', [[[$x, $y], [$x + 10, $y], [$x + 10, $y + 10], [$x, $y + 10]]], FillRule::NON_ZERO, 0xFF0000FF, $surface];
    $commands = [['clear', 0x000000FF], $square(2, 2), $square(80, 80)];
    $regions = [new Region(0, 0, 20, 20), new Region(70, 70, 30, 30)];

    $out = Boxes::scissor($commands, $regions);

    // each region: its clear, and only the square inside it
    expect($out)->toHaveCount(4)
        ->and(array_column($out, 0))->toBe(['clear', 'path', 'clear', 'path'])
        ->and($out[1][1][0][0])->toBe([2.0, 2.0])
        ->and($out[1][4])->toEqual(new Region(0, 0, 20, 20))
        ->and($out[3][1][0][0])->toBe([80.0, 80.0])
        ->and($out[3][4])->toEqual(new Region(70, 70, 30, 30));
});
