<?php

namespace Surface\Contracts\Graphics;

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;

/**
 * A stage that shows pixels in more byte orders than RGBA8, as they are: nothing reorders them on
 * the way. An engine whose surface already holds one of hostFormats() hands its pixels over in
 * that format with displayPixels(); any other engine goes on giving RGBA8 to display().
 *
 * Window systems take a format with a buffer of pixels (Core Graphics' bitmap info, an SDL
 * texture's format, a Wayland buffer's): this is that, for a stage.
 */
interface HostFormatStage extends ActorStage
{
    /** @return list<FormatSpec> the host formats it shows as they are, the one it would rather have first */
    public function hostFormats(): array;

    /**
     * Shows these pixels over the whole stage, until the next call, as display() does.
     *
     * @param string $pixels $width x $height pixels, four bytes each in $format's order, rows from the top; the alpha byte is not read
     * @param FormatSpec $format one of hostFormats()
     */
    public function displayPixels(string $pixels, int $width, int $height, FormatSpec $format): void;
}
