<?php

namespace VelvetGE\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use VelvetGE\Contracts\Framebuffers\PagedFramebuffer as PagedFramebufferContract;
use VelvetGE\Contracts\Framebuffers\RingFramebuffer as RingFramebufferContract;

trait ReadsBlitSources
{
    /**
     * What a blit takes from a source: its RGBA8 bytes, their width and height,
     * and how far down the source they start. A paged source gives its current
     * page; every other source gives its whole surface.
     *
     * @return array{string, int, int, int}
     */
    protected function blitSource(InstanceThatManagesAFramebuffer $source): array
    {
        if ($source instanceof PagedFramebufferContract) {
            $page = $source->pageRegion($source->page());

            return [$source->toRgba8(), $page->width, $page->height, $page->y];
        }

        return [$source->toRgba8(), $source->viewportWidth(), $source->viewportHeight(), 0];
    }

    /**
     * Where $source's pixels are, for a library to read them in place: its memory ticket, or
     * null when it lends none. A ring is a swapchain: memory() is its back buffer, where drawing
     * lands; read as a source it is its front buffer, the frame it shows. A paged source holds one
     * page at a time, so it lends nothing to read.
     */
    protected function sourceMemory(InstanceThatManagesAFramebuffer $source): ?PixelMemory
    {
        if ($source instanceof PagedFramebufferContract) {
            return null;
        }

        return ($source instanceof RingFramebufferContract ? $source->front() : $source)->memory();
    }
}
