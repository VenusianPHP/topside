<?php

declare(strict_types=1);

namespace Venusian\Topside\Tests\Fixtures;

use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Raycasting\DirectMarcher;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Marcher;
use Surface\VelvetGE\Drivers\Raycasting\MarcherRaycastingDriver;
use Surface\VelvetGE\Raycasting\CLandMarcher;
use Surface\VelvetGE\Raycasting\PHPLandMarcher;

/**
 * A library binding's marcher that can write a view into a store's memory. Its rays and pictures are the PHP
 * marcher's; ext-raycast stands in for the library where it draws in place. $asked lists what it was asked for.
 */
final class LibraryMarcher implements DirectMarcher
{
    /** @var list<string> */
    public static array $asked = [];

    private PHPLandMarcher $php;

    private CLandMarcher $native;

    public function __construct(Grid $grid)
    {
        $this->php = new PHPLandMarcher($grid);
        $this->native = new CLandMarcher($grid);
    }

    public function ray(float $x, float $y, float $dx, float $dy, float $reach): string
    {
        return $this->php->ray($x, $y, $dx, $dy, $reach);
    }

    public function rays(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $columns, float $reach): string
    {
        return $this->php->rays($x, $y, $dirX, $dirY, $planeX, $planeY, $columns, $reach);
    }

    public function picture(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $width, int $height, array $walls, int $ceiling, int $floor, int $shade, float $reach): string
    {
        self::$asked[] = 'picture';

        return $this->php->picture($x, $y, $dirX, $dirY, $planeX, $planeY, $width, $height, $walls, $ceiling, $floor, $shade, $reach);
    }

    public function drawsInto(PixelMemory $memory): bool
    {
        return $this->native->drawsInto($memory);
    }

    public function pictureInto(PixelMemory $memory, Region $where, float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $width, int $height, array $walls, int $ceiling, int $floor, int $shade, float $reach): void
    {
        self::$asked[] = 'pictureInto';

        $this->native->pictureInto($memory, $where, $x, $y, $dirX, $dirY, $planeX, $planeY, $width, $height, $walls, $ceiling, $floor, $shade, $reach);
    }
}

/** A library binding's raycasting driver: a name and a marcher. */
final class LibraryRaycastingDriver extends MarcherRaycastingDriver
{
    public function driver(): string
    {
        return 'library';
    }

    protected function marcher(Grid $grid): Marcher
    {
        return new LibraryMarcher($grid);
    }
}
