<?php

namespace Surface\VelvetGE\Raycasting;

use RaycastGrid;
use Surface\Contracts\Rendering\Framebuffers\ChannelOrder;
use Surface\Contracts\Rendering\Framebuffers\FramebufferException;
use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Framebuffers\ScanDirection;
use Surface\Contracts\Rendering\Raycasting\DirectMarcher;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\VelvetGE\Framebuffers\Layout;
use ValueError;

/**
 * The grid walk in C: one RaycastGrid of ext-raycast. Every operation is one call into the
 * extension; what it refuses with ValueError is rethrown as RaycastException.
 */
class CLandMarcher implements DirectMarcher
{
    private RaycastGrid $native;

    public function __construct(Grid $grid)
    {
        $this->native = new RaycastGrid($grid->width, $grid->height, $grid->cells);
    }

    public function ray(float $x, float $y, float $dx, float $dy, float $reach): string
    {
        try {
            return $this->native->cast($x, $y, $dx, $dy, $reach);
        } catch (ValueError $e) {
            throw new RaycastException($e->getMessage(), previous: $e);
        }
    }

    public function rays(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $columns, float $reach): string
    {
        try {
            return $this->native->fan($x, $y, $dirX, $dirY, $planeX, $planeY, $columns, $reach);
        } catch (ValueError $e) {
            throw new RaycastException($e->getMessage(), previous: $e);
        }
    }

    public function picture(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $width, int $height, array $walls, int $ceiling, int $floor, int $shade, float $reach): string
    {
        try {
            return $this->native->view($x, $y, $dirX, $dirY, $planeX, $planeY, $width, $height, $walls, $ceiling, $floor, $shade, $reach);
        } catch (ValueError $e) {
            throw new RaycastException($e->getMessage(), previous: $e);
        }
    }

    /** Four bytes a pixel in any of the four channel orders, top row first: ext-raycast writes those. */
    public function drawsInto(PixelMemory $memory): bool
    {
        try {
            return Layout::of($memory->format) === Layout::RGBA8888
                && $memory->format->scan_direction === ScanDirection::TOP_TO_BOTTOM
                && $memory->stride >= $memory->width * 4;
        } catch (FramebufferException) {
            return false;    // not a layout at all
        }
    }

    public function pictureInto(PixelMemory $memory, Region $where, float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $width, int $height, array $walls, int $ceiling, int $floor, int $shade, float $reach): void
    {
        if ($where->x < 0 || $where->y < 0 || $where->right() > $memory->width || $where->bottom() > $memory->height) {
            throw RaycastException::outside($where->x, $where->y, $where->width, $where->height, $memory->width, $memory->height);
        }
        $order = match (Layout::channelOrder($memory->format)) {
            ChannelOrder::BGRA => \RAYCAST_ORDER_BGRA,
            ChannelOrder::ARGB => \RAYCAST_ORDER_ARGB,
            ChannelOrder::ABGR => \RAYCAST_ORDER_ABGR,
            default => \RAYCAST_ORDER_RGBA,
        };

        try {
            $this->native->viewInto($x, $y, $dirX, $dirY, $planeX, $planeY, $width, $height, $walls, $ceiling, $floor, $memory->address + $where->y * $memory->stride + $where->x * 4, $memory->stride, $shade, $reach, $order);
        } catch (ValueError $e) {
            throw new RaycastException($e->getMessage(), previous: $e);
        }
    }
}
