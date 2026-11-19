<?php

namespace VelvetGE\Contracts\Glyphs;

use Surface\Contracts\Rendering\deprecated\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Region;

/**
 * Where a glyph module keeps glyphs: what VelvetGE's GlyphCache asks of a module. VelvetGE decides
 * which glyphs to keep, what each looks like and where it lands; the store keeps each glyph's
 * coverage where the framebuffer's pixels live, and paints the glyphs placed.
 *
 * A store's glyphs never come back to the cache: keep() answers a number, paint() paints by number.
 */
interface GlyphStore
{
    /** Whether it can paint into $target as it is. */
    public function drawsInto(InstanceThatManagesAFramebuffer $target): bool;

    /**
     * One glyph kept: its contours filled by the non-zero rule inside the box (0, 0, width, height),
     * with the edges the store was made with.
     *
     * @param  list<list<float>>  $contours  each a flat x, y, x, y… list inside the box, closed implicitly
     * @param  int  $width  1..65535
     * @param  int  $height  1..65535
     * @return int|null its number; null when it covers no pixel and nothing is kept
     */
    public function keep(array $contours, int $width, int $height): ?int;

    /** A glyph dropped: its number may be handed out again. */
    public function forget(int $glyph): void;

    /** The bytes its glyphs take. */
    public function size(): int;

    /**
     * Kept glyphs painted into $target in one colour, in order, each with its box's top-left at
     * (x, y), nothing outside $clip.
     *
     * @param  list<int>  $placements  a flat list number, x, y, number, x, y…
     * @param  Region  $clip  inside $target
     * @return list<Region|null> what it wrote straight into $target's memory(); empty when it painted through $target's own calls
     */
    public function paint(array $placements, int $rgba, Region $clip, InstanceThatManagesAFramebuffer $target): array;
}
