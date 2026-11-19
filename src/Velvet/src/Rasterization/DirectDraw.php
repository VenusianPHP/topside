<?php

namespace VelvetGE\Rasterization;

use Surface\Contracts\Rendering\deprecated\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Region;
use Surface\Contracts\Rendering\deprecated\Rasterization\DirectScanner;
use Surface\Contracts\Rendering\deprecated\Rasterization\MaskScanner;
use Surface\Contracts\Rendering\deprecated\Rasterization\Scanner;
use Surface\Contracts\Rendering\deprecated\Raycasting\DirectMarcher;
use Surface\Contracts\Rendering\deprecated\Raycasting\Marcher;
use VelvetGE\Contracts\Framebuffers\CompositingFramebuffer;

/**
 * How VelvetGE gets a producer's drawing onto a framebuffer. It is the one place a rasterizer's
 * scanner or a raycaster's marcher meets a pixel store, and neither knows the other: a library's
 * producer draws on another library's store through here.
 *
 * In place, when the producer can draw into the store's memory as it is. Staged otherwise: the
 * producer answers what it has (coverage, or a picture) and the store lands it with its own
 * native calls. Either way the framebuffer records what changed.
 */
final class DirectDraw
{
    /**
     * Scanner ops painted on $target in order, each at its colour. Every op is one the scanner
     * answers itself: the rasterizer lowered the shapes for this scanner.
     *
     * In place when the scanner is a DirectScanner that draws into the target's memory; as coverage
     * masks when it is a MaskScanner; as spans otherwise. Without $direct everything goes as spans:
     * the path every other one is held to.
     *
     * @param  list<list<mixed>>  $ops  as BatchScanner::batch() takes them, already checked
     * @param  list<int>  $colours  0xRRGGBBAA, one an op
     */
    public static function shapes(Scanner $scanner, array $ops, array $colours, CompositingFramebuffer $target, bool $direct = true): void
    {
        $memory = $direct && $scanner instanceof DirectScanner ? $target->memory() : null;

        match (true) {
            ! is_null($memory) && $scanner->drawsInto($memory) => $target->drewInto(...$scanner->fill($ops, $colours, $memory)),
            $direct && $scanner instanceof MaskScanner => self::masks($scanner, $ops, $colours, $target),
            default => self::spans($scanner, $ops, $colours, $target),
        };
    }

    /**
     * A raycaster's picture put on $target over $where, a rect inside it: drawn in place when the
     * marcher is a DirectMarcher that draws into the target's memory, else answered as RGBA8 and
     * landed by the store.
     *
     * @param  array{float, float, float, float, float, float, int, int, array, int, int, int, float}  $picture
     *         the arguments Marcher::picture() takes, already checked, sized as $where
     */
    public static function view(Marcher $marcher, array $picture, InstanceThatManagesAFramebuffer $target, Region $where): void
    {
        $memory = $marcher instanceof DirectMarcher ? $target->memory() : null;

        if (! is_null($memory) && $marcher->drawsInto($memory)) {
            $marcher->pictureInto($memory, $where, ...$picture);
            $target->drewInto($where);

            return;
        }

        $target->writeRgba8($marcher->picture(...$picture), $where->width, $where->height, $where->x, $where->y);
    }

    private static function spans(Scanner $scanner, array $ops, array $colours, CompositingFramebuffer $target): void
    {
        foreach (Ops::scan($scanner, $ops) as $i => $spans) {
            if ($spans !== '') {
                $target->paintSpans($spans, $colours[$i]);
            }
        }
    }

    private static function masks(MaskScanner $scanner, array $ops, array $colours, CompositingFramebuffer $target): void
    {
        foreach ($ops as $i => $op) {
            $mask = $scanner->mask($op);
            if (! is_null($mask)) {
                $target->paintMasks([$mask], $colours[$i], $scanner->clip());
            }
        }
    }
}
