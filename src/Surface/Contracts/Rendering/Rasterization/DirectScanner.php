<?php

namespace Surface\Contracts\Rendering\Rasterization;

use Surface\Contracts\Rendering\Framebuffers\Region;

/**
 * Optional: a scanner that paints straight into a framebuffer's own memory, with no span bytes
 * built in between. A sink is the pixel store's own native buffer, as Framebuffer::spanSink()
 * answers it, or a PixelMemory: where the store's bytes are, in which format. A scanner that
 * takes a PixelMemory paints into the store of any library. A scanner says which sinks it can
 * paint into; a batch headed anywhere else goes through Framebuffer::paintSpans().
 *
 * What it paints is what paintSpans() would paint from its own spans: the same pixels blended
 * the same way, inside the scanner's clip.
 */
interface DirectScanner extends Scanner
{
    /** Whether this scanner can paint into $sink. */
    public function paintsInto(object $sink): bool;

    /**
     * Ops painted into $sink in order, each at its colour. Only ops this scanner itself has are given:
     * paths always, and those of the tiers it implements.
     *
     * @param  list<list<mixed>>  $ops  as BatchScanner::batch() takes them
     * @param  list<int>  $colours  0xRRGGBBAA, one an op
     * @param  object  $sink  one paintsInto() answered true for
     * @return list<Region|null> what each op painted; null for one that painted nothing
     */
    public function fill(array $ops, array $colours, object $sink): array;
}
