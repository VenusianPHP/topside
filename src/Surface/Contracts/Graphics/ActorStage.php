<?php

namespace Surface\Contracts\Graphics;

/**
 * Somewhere a graphics engine's frames are shown: a canvas in a toolkit window, a staged window,
 * an embedded display.
 *
 * A stage is handed finished pixels and shows them. It draws nothing itself.
 *
 * A stage may also lend what it is to the platform, for an engine that can draw there itself
 * (a GPU engine putting its own layer on a view). The stage does not know who asks or why.
 */
interface ActorStage
{
    /**
     * Its size in pixels as it is now: its layout size times pixelScale(). A stage that has not
     * been laid out yet is [0, 0].
     *
     * @return array{int, int} width, height
     */
    public function pixelSize(): array;

    /** Pixels to one unit of its layout: 2.0 on a display of twice the density. */
    public function pixelScale(): float;

    /**
     * Shows these pixels over the whole stage, until the next call. Pixels of another size than
     * pixelSize() are stretched to fit.
     *
     * @param string $rgba8 $width x $height pixels, four bytes each, red first, rows from the top; the alpha byte is not read
     */
    public function display(string $rgba8, int $width, int $height): void;

    /**
     * What the stage is to the platform, for an engine that draws there itself: an NSView on
     * macOS. Null when it lends nothing: an engine then hands it pixels through display().
     *
     * The stage stays its owner's. An engine adds to it and takes away what it added; it does
     * not replace what the stage already shows.
     */
    public function nativeSurface(): ?object;
}
