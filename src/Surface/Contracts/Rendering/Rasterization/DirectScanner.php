<?php

namespace Surface\Contracts\Rendering\Rasterization;

use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Framebuffers\Region;

/**
 * Optional: a scanner that blends its shapes straight into a pixel store's memory, with no
 * coverage built in between. It says which memory it can draw into, by what the memory is (its
 * format, its row length), never by which library holds it.
 *
 * What it draws is what Framebuffer::paintSpans() would paint from its own spans: the same pixels
 * blended the same way, inside the scanner's clip. The engine asks drawsInto() first and calls
 * fill() only after a true; a false sends the shapes through coverage instead.
 */
interface DirectScanner extends Scanner
{
    /** Whether this scanner can draw into $memory as it is: its format, its rows, colours straight. */
    public function drawsInto(PixelMemory $memory): bool;

    /**
     * Ops drawn into $memory in order, each at its colour. Only ops this scanner itself has are given:
     * paths always, and those of the tiers it implements.
     *
     * @param  list<list<mixed>>  $ops  as BatchScanner::batch() takes them
     * @param  list<int>  $colours  0xRRGGBBAA, one an op
     * @return list<Region|null> what each op touched; null for one that drew nothing
     */
    public function fill(array $ops, array $colours, PixelMemory $memory): array;
}
