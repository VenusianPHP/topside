<?php

namespace Surface\Contracts\Rendering\Framebuffers;

/**
 * Where the bytes live. Mints the five buffer kinds; callers never name a buffer class.
 *
 * A driver that cannot run in this PHP (its extension is not loaded, or is too old) throws a
 * FramebufferException from its constructor: the 'auto' driver then takes the next one.
 */
interface FramebufferDriver
{
    /** The name the driver is registered under: 'native' and 'ext' are VelvetGE's, a library binding has its own. */
    public function driver(): string;

    public function full(FormatSpec $format, int $width, int $height): Framebuffer;

    public function dirty(FormatSpec $format, int $width, int $height): DamageTrackingFramebuffer;

    public function epaper(FormatSpec $format, int $width, int $height): ePaperFramebuffer;

    public function paged(FormatSpec $format, int $width, int $height, int $page_rows): PagedFramebuffer;

    public function ring(FormatSpec $format, int $width, int $height, int $frames): RingFramebuffer;
}