<?php

namespace Surface\VelvetGE\Drivers\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\PixelStoreMinter;
use Surface\Rendering\Framebuffers\FramebufferDriver;
use Surface\VelvetGE\Framebuffers\DirtyFramebuffer;
use Surface\VelvetGE\Framebuffers\ePaperFramebuffer;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\VelvetGE\Framebuffers\FullFramebuffer;
use Surface\VelvetGE\Framebuffers\PagedFramebuffer;
use Surface\VelvetGE\Framebuffers\RingFramebuffer;

/**
 * The five kinds over one kind of PixelStore. A driver says its name and mints its store;
 * the kinds here do the damage, the pages and the frames.
 *
 * A library binding extends this with two methods and one check:
 *
 *     class PixmanFramebufferDriver extends StoreFramebufferDriver
 *     {
 *         public function __construct()
 *         {
 *             if (! extension_loaded('pixman')) {
 *                 throw FramebufferException::extensionMissing('pixman', '0.1.0');
 *             }
 *         }
 *
 *         public function driver(): string { return 'pixman'; }
 *
 *         public function mint(FormatSpec $format, int $width, int $height): PixelStore
 *         {
 *             return new PixmanPixelStore($format, $width, $height);
 *         }
 *     }
 *
 * and registers it by name in its service provider's boot():
 *
 *     Framebuffers::manager()->extend('pixman', fn () => new PixmanFramebufferDriver());
 *
 * The store needs only the core, PixelStore: hold the pixels, read and write them, move them.
 * That alone is a whole framebuffer, because a kind completes the store it is minted: painting
 * and effects then run with VelvetGE's arithmetic over the store's pixels. A store adds
 * PaintingPixelStore or EffectsPixelStore for what its library does itself, and those calls go
 * straight to it.
 */
abstract class StoreFramebufferDriver extends FramebufferDriver implements PixelStoreMinter
{
    public function full(FormatSpec $format, int $width, int $height): FullFramebuffer
    {
        return new FullFramebuffer($this, $format, $width, $height);
    }

    public function dirty(FormatSpec $format, int $width, int $height): DirtyFramebuffer
    {
        return new DirtyFramebuffer($this, $format, $width, $height);
    }

    public function epaper(FormatSpec $format, int $width, int $height): ePaperFramebuffer
    {
        return new ePaperFramebuffer($this, $format, $width, $height);
    }

    public function paged(FormatSpec $format, int $width, int $height, int $page_rows): PagedFramebuffer
    {
        return new PagedFramebuffer($this, $format, $width, $height, $page_rows);
    }

    public function ring(FormatSpec $format, int $width, int $height, int $frames): RingFramebuffer
    {
        return new RingFramebuffer($this, $format, $width, $height, $frames);
    }
}
