<?php

namespace Surface\Contracts\Rendering\Painting;

use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;

/**
 * Carries out drawing commands straight into a framebuffer's memory: what a painting library, or
 * an extension, brings. A painter records a frame as commands (Surface\Contracts\Rendering\Recording\Recorder
 * lists their shapes); a brush is handed the commands it takes, in order, and paints them with its
 * own geometry and its own blending.
 *
 * A brush need not take every command, or draw into every memory. VelvetGE's painter gives a brush
 * each run of commands it takes, where it can draw, and carries out the rest itself in between, so
 * the frame is drawn whole and in order whatever the brush leaves out.
 *
 * A brush's pixels are its own arithmetic: they are held to VelvetGE's by a tolerance, not byte for
 * byte.
 */
interface Brush
{
    /** Whether this brush can draw into $memory as it is: its format, its rows, colours straight. */
    public function drawsInto(PixelMemory $memory): bool;

    /**
     * Whether this brush carries out $command with $edges. Asked of each command before paint() is
     * given it: a brush may take a kind in part (an image whose source it can read, say), or only
     * with the edges it can draw (a library that always anti-aliases refuses Edges::HARD).
     *
     * @param  array<int, mixed>  $command
     */
    public function takes(array $command, Edges $edges): bool;

    /**
     * The commands drawn into $memory in order. Each command's last entry is its clip; nothing is
     * drawn outside that clip or outside $rows.
     *
     * @param  list<array<int, mixed>>  $commands  every one a command takes() answered true for, with these $edges
     * @return list<Region|null> the regions it wrote, in any number; a null is passed over
     */
    public function paint(array $commands, Region $rows, PixelMemory $memory, Edges $edges): array;
}
