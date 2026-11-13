<?php

namespace Surface\VelvetGE\Rasterize;

use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\Rasterization\DirectScanner;

/**
 * fillBatch() for a rasterizer whose scanner may paint straight into the target. Shapes are
 * painted in order either way. A run of shapes the scanner itself has goes into the target's
 * sink in one call; a run it leaves to VelvetGE goes through paintSpans(). With no sink, or a
 * sink the scanner cannot paint into, the whole batch goes through paintSpans().
 *
 * The scanner and the store need not know each other: see sinkFor().
 */
trait PaintsDirect
{
    public function fillBatch(array $shapes, Framebuffer $target, bool $direct = true): void
    {
        $scanner = $this->scanner instanceof CompletingScanner ? $this->scanner->scanner() : $this->scanner;
        $sink = $direct && $scanner instanceof DirectScanner ? self::sinkFor($scanner, $target) : null;
        if (is_null($sink)) {
            parent::fillBatch($shapes, $target, $direct);

            return;
        }

        [$ops, $colours] = $this->lower($shapes);
        for ($from = 0, $count = count($ops); $from < $count; $from = $to) {
            $own = $this->owns($ops[$from]);
            for ($to = $from + 1; $to < $count && $this->owns($ops[$to]) === $own; $to++);
            $run = array_slice($ops, $from, $to - $from);
            $paint = array_slice($colours, $from, $to - $from);

            if ($own) {
                $target->paintedSpans($scanner->fill($run, $paint, $sink));

                continue;
            }
            foreach ($this->scanner->batch($run) as $i => $spans) {
                if ($spans !== '') {
                    $target->paintSpans($spans, $paint[$i]);
                }
            }
        }
    }

    /**
     * The first form of the target's sink the scanner can paint into: the sink as the store answers
     * it, then, for ext-fb's buffer, the same bytes as plain memory. So ext-rasterize paints into a
     * library's store that says where its bytes are, and a library's scanner into ext-fb's.
     */
    private static function sinkFor(DirectScanner $scanner, Framebuffer $target): ?object
    {
        $sink = $target->spanSink();
        if (is_null($sink)) {
            return null;
        }
        if ($scanner->paintsInto($sink)) {
            return $sink;
        }
        if ($sink instanceof \FbBuffer) {
            $memory = new PixelMemory($sink->pointer(), $sink->width(), $sink->height(), intdiv($sink->size(), $sink->height()), $target->hostFormat(), $sink);

            return $scanner->paintsInto($memory) ? $memory : null;
        }

        return null;
    }

    /** Whether the rasterizer's own scanner has $op, so it may paint it direct. */
    private function owns(array $op): bool
    {
        return ! $this->scanner instanceof CompletingScanner || $this->scanner->owns($op);
    }
}
