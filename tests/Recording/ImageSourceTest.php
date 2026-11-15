<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Painting\PaintTarget;
use Surface\NutsAndBolts\Affine;
use Surface\Rendering\Recording\Boxes;

// A GPU engine's framebuffer is a PaintTarget but not a Rendering Framebuffer: an image drawn from one must be
// boxed as any other, or the second frame that draws it cannot be compared with the first.
it('boxes an image whose source is any paint target, and finds what changed between two frames of it', function (): void {
    $source = new class implements PaintTarget
    {
        public function viewportWidth(): int
        {
            return 20;
        }

        public function viewportHeight(): int
        {
            return 10;
        }

        public function damageGranularity(): DamageGranularity
        {
            return DamageGranularity::pixel(20, 10);
        }

        public function preservesContentsOnPresent(): bool
        {
            return true;
        }

        public function scroll(Region $region, int $dx, int $dy): void {}
    };
    $surface = new Region(0, 0, 100, 100);
    $at = fn (float $x): array => ['image', $source, Affine::translation($x, 5.0), 255, null, [], $surface];

    expect(Boxes::extent($at(10.0)))->toBe([9.0, 4.0, 31.0, 16.0])
        ->and(Boxes::changed([$at(10.0)], [$at(40.0)], $surface, DamageGranularity::pixel(100, 100)))->not->toBe([]);
});
