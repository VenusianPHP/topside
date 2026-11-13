<?php

namespace Surface\VelvetGE;

use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\CompleteScanner;
use Surface\Contracts\Rendering\Rasterization\DirectScanner;
use Surface\Contracts\Rendering\Rasterization\MaskScanner;
use Surface\Contracts\Rendering\Raycasting\DirectMarcher;
use Surface\Contracts\Rendering\Raycasting\Marcher;
use Surface\VelvetGE\Rasterize\CompletingScanner;

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
     * Scanner ops painted on $target in order, each at its colour.
     *
     * A run of ops the driver's scanner itself has goes in place when the scanner is a DirectScanner
     * that draws into the target's memory; as coverage masks when it is a MaskScanner; as spans
     * otherwise. A run it leaves to VelvetGE goes as spans from VelvetGE's scanner. Without $direct
     * everything goes as spans: the path every other one is held to.
     *
     * @param  list<list<mixed>>  $ops  as BatchScanner::batch() takes them, already checked
     * @param  list<int>  $colours  0xRRGGBBAA, one an op
     */
    public static function shapes(CompleteScanner $scanner, array $ops, array $colours, Framebuffer $target, bool $direct = true): void
    {
        $own = $scanner instanceof CompletingScanner ? $scanner->scanner() : $scanner;
        $memory = $direct && $own instanceof DirectScanner ? $target->memory() : null;
        $inPlace = ! is_null($memory) && $own->drawsInto($memory);
        $masked = $direct && ! $inPlace && $own instanceof MaskScanner;

        if (! $inPlace && ! $masked) {
            self::spans($scanner, $ops, $colours, $target);

            return;
        }

        // Ops stay in order: consecutive ops the driver's scanner has are one run, the ones it leaves to VelvetGE another.
        $has = fn (array $op): bool => ! $scanner instanceof CompletingScanner || $scanner->owns($op);
        for ($from = 0, $count = count($ops); $from < $count; $from = $to) {
            $owned = $has($ops[$from]);
            for ($to = $from + 1; $to < $count && $has($ops[$to]) === $owned; $to++);
            $run = array_slice($ops, $from, $to - $from);
            $paint = array_slice($colours, $from, $to - $from);

            match (true) {
                ! $owned => self::spans($scanner, $run, $paint, $target),
                $inPlace => $target->drewInto(...$own->fill($run, $paint, $memory)),
                default => self::masks($own, $run, $paint, $target),
            };
        }
    }

    /**
     * A raycaster's picture put on $target over $where, a rect inside it: drawn in place when the
     * marcher is a DirectMarcher that draws into the target's memory, else answered as RGBA8 and
     * landed by the store.
     *
     * @param  array{float, float, float, float, float, float, int, int, array, int, int, int, float}  $picture
     *         the arguments Marcher::picture() takes, already checked, sized as $where
     */
    public static function view(Marcher $marcher, array $picture, Framebuffer $target, Region $where): void
    {
        $memory = $marcher instanceof DirectMarcher ? $target->memory() : null;

        if (! is_null($memory) && $marcher->drawsInto($memory)) {
            $marcher->pictureInto($memory, $where, ...$picture);
            $target->drewInto($where);

            return;
        }

        $target->writeRgba8($marcher->picture(...$picture), $where->width, $where->height, $where->x, $where->y);
    }

    private static function spans(CompleteScanner $scanner, array $ops, array $colours, Framebuffer $target): void
    {
        foreach ($scanner->batch($ops) as $i => $spans) {
            if ($spans !== '') {
                $target->paintSpans($spans, $colours[$i]);
            }
        }
    }

    private static function masks(MaskScanner $scanner, array $ops, array $colours, Framebuffer $target): void
    {
        foreach ($ops as $i => $op) {
            $mask = $scanner->mask($op);
            if (! is_null($mask)) {
                $target->paintMasks([$mask], $colours[$i], $scanner->clip());
            }
        }
    }
}
