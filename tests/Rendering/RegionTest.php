<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\RegionException;
use Surface\Rendering\Region;

/*
 * Region is the rectangle a clip and a written area are given as. A drawer and a stage rely on its edges
 * and on what two rectangles share.
 */

it('refuses a rectangle with no pixel in it', function (int $width, int $height): void {
    expect(fn () => new Region(0, 0, $width, $height))->toThrow(RegionException::class, "got {$width}x{$height}");
})->with([[0, 5], [5, 0], [-1, 5], [5, -1]]);

it('has its right and bottom one past its last column and row', function (): void {
    $region = new Region(10, 20, 30, 40);

    expect($region->right())->toBe(40)
        ->and($region->bottom())->toBe(60)
        ->and($region->firstColumnPastTheRightEdge())->toBe(40)
        ->and($region->firstRowPastTheBottomEdge())->toBe(60);
});

it('intersects to the pixels both rectangles cover', function (): void {
    $shared = (new Region(0, 0, 10, 10))->intersect(new Region(6, 4, 10, 10));

    expect([$shared->x, $shared->y, $shared->width, $shared->height])->toBe([6, 4, 4, 6]);
});

it('has no intersection with a rectangle that only touches it or is apart from it', function (Region $other): void {
    expect((new Region(0, 0, 10, 10))->intersect($other))->toBeNull();
})->with([
    'touches on the right' => [new Region(10, 0, 5, 5)],
    'touches below' => [new Region(0, 10, 5, 5)],
    'apart' => [new Region(50, 50, 5, 5)],
]);

it('unions to the smallest rectangle that holds both', function (): void {
    $both = (new Region(10, 10, 5, 5))->union(new Region(40, 2, 10, 4));

    expect([$both->x, $both->y, $both->width, $both->height])->toBe([10, 2, 40, 13]);
});
