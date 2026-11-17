<?php

namespace Surface\Contracts\Rendering\Glyphs;

use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Rendering\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Typography\TypographyException;

/**
 * Draws 'glyphs' commands into a framebuffer. It keeps what it makes of each glyph where the
 * framebuffer's pixels live, so a glyph drawn again is not made again; it makes a glyph from what
 * the glyph's FontFace answers.
 *
 * A glyph's pixels never cross from C into PHP: a painter that makes them in C draws them in C, and
 * says so through drawsInto().
 */
interface GlyphPainter
{
    /** Whether it can draw into $target as it is. */
    public function drawsInto(InstanceThatManagesAFramebuffer $target): bool;

    /**
     * A 'glyphs' command drawn into $target, nothing outside $clip.
     *
     * @param  array<int, mixed>  $command  ['glyphs', runs, matrix, rgba, reach, clip], as a Recorder records it
     * @param  Region  $clip  inside $target, already cut by the command's clip
     * @return list<Region|null> what it wrote straight into $target's memory(), for drewInto(); empty when it drew through $target's own calls
     *
     * @throws DrawingException When it cannot draw into $target (drawsInto() said so).
     * @throws TypographyException When a run's source cannot be read.
     */
    public function draw(array $command, Region $clip, InstanceThatManagesAFramebuffer $target): array;
}
