<?php

namespace Surface\Contracts\Rendering;

/**
 * What a rendering library implements: it draws a recorder's commands into pixel memory, its own way.
 * A command is a list, [kind, ...], with its points in frame pixels. Whole numbers are pixel edges.
 * A color is 0xRRGGBBAA, not multiplied by its alpha. The last entry of every command but 'clear' is its clip,
 * a Region: the command writes no pixel outside it.
 */
interface Drawer
{
    public function name(): string;

    /**
     * Whether it can draw into memory laid out like this.
     */
    public function drawsInto(PixelMemory $memory): bool;

    /**
     * Whether it draws this command.
     */
    public function takes(array $command): bool;

    /**
     * Draws the commands it takes into the memory, in order, and passes over the ones it does not.
     * It writes no pixel outside $clip.
     *
     * @param list<array> $commands
     *
     * @throws DrawerException for memory it does not draw into
     */
    public function draw(array $commands, PixelMemory $into, Region $clip): void;
}
