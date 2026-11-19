<?php

namespace Surface\Rendering\CommandRecorders;

use PixelQueue;
use PixelQueueException;
use Surface\Contracts\Rendering\Enums\FillRule;
use Surface\Contracts\Rendering\Recording\Recorder;
use Surface\Contracts\Rendering\Recording\Enums\CommandReach;
use Surface\Contracts\Rendering\Recording\Enums\RecordingError;
use Surface\Contracts\Rendering\Recording\Exceptions\CommandRecorderException;

/**
 * The recorder in C: ext-comrec's PixelQueue, call for call. Path commands carry FillRule::NON_ZERO or
 * FillRule::EVEN_ODD, and a shape it refuses comes out as a CommandRecorderException.
 */
final class CCommandRecorder implements Recorder
{
    private PixelQueue $queue;

    public function __construct()
    {
        if (! extension_loaded('comrec')) {
            throw CommandRecorderException::extensionMissing();
        }
        $this->queue = new PixelQueue(FillRule::NON_ZERO, FillRule::EVEN_ODD);
    }

    /** One shape or concat() on the queue; its refusal comes out as the recorder's own. */
    private function record(string $method, array $arguments): void
    {
        try {
            $this->queue->{$method}(...$arguments);
        } catch (PixelQueueException $refused) {
            throw new CommandRecorderException(RecordingError::from($refused->getCode()), $refused->what, $refused->value, $refused);
        }
    }

    public function begin(int $width, int $height, mixed $clip): void
    {
        $this->queue->begin($width, $height, $clip);
    }

    public function recording(): bool
    {
        return $this->queue->recording();
    }

    public function abandon(): void
    {
        $this->queue->abandon();
    }

    public function finish(): array
    {
        return $this->queue->finish();
    }

    public function push(): void
    {
        $this->queue->push();
    }

    public function pop(): void
    {
        $this->queue->pop();
    }

    public function depth(): int
    {
        return $this->queue->depth();
    }

    public function concat(float $a, float $b, float $c, float $d, float $e, float $f): void
    {
        $this->record('concat', [$a, $b, $c, $d, $e, $f]);
    }

    public function matrix(): array
    {
        return $this->queue->matrix();
    }

    public function clip(mixed $clip, int $x, int $y, int $width, int $height): void
    {
        $this->queue->clip($clip, $x, $y, $width, $height);
    }

    public function clipValue(): mixed
    {
        return $this->queue->clipValue();
    }

    public function enter(mixed $clip, int $x, int $y, int $width, int $height): void
    {
        $this->queue->enter($clip, $x, $y, $width, $height);
    }

    public function leave(): array
    {
        return $this->queue->leave();
    }

    public function clear(int $rgba): void
    {
        $this->record('clear', [$rgba]);
    }

    public function fillRect(float $x, float $y, float $width, float $height, int|array $rgba): void
    {
        $this->record('fillRect', [$x, $y, $width, $height, $rgba]);
    }

    public function strokeRect(float $x, float $y, float $width, float $height, int|array $rgba, float $stroke): void
    {
        $this->record('strokeRect', [$x, $y, $width, $height, $rgba, $stroke]);
    }

    public function line(float $x0, float $y0, float $x1, float $y1, int|array $rgba, float $stroke): void
    {
        $this->record('line', [$x0, $y0, $x1, $y1, $rgba, $stroke]);
    }

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, int|array $rgba): void
    {
        $this->record('fillTriangle', [$x0, $y0, $x1, $y1, $x2, $y2, $rgba]);
    }

    public function fillPath(array $ops, FillRule $rule, int|array $rgba): void
    {
        $this->record('fillPath', [$ops, $rule === FillRule::EVEN_ODD, $rgba]);
    }

    public function strokePath(array $ops, int|array $rgba, float $stroke): void
    {
        $this->record('strokePath', [$ops, $rgba, $stroke]);
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, int|array $rgba): void
    {
        $this->record('fillEllipse', [$cx, $cy, $rx, $ry, $rgba]);
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, int|array $rgba, float $stroke): void
    {
        $this->record('strokeEllipse', [$cx, $cy, $rx, $ry, $rgba, $stroke]);
    }

    public function roundRect(float $x, float $y, float $width, float $height, array $radii, int|array $fill, int|array|null $border, float $borderWidth): void
    {
        $this->record('roundRect', [$x, $y, $width, $height, $radii, $fill, $border, $borderWidth]);
    }

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, int $rgba, float $dx, float $dy): void
    {
        $this->record('shadow', [$x, $y, $width, $height, $radius, $blur, $rgba, $dx, $dy]);
    }

    public function glyphs(array $runs, float $x, float $y, ?array $ink, int $rgba): void
    {
        $this->record('glyphs', [$runs, $x, $y, $ink, $rgba]);
    }

    public function lower(array $command, string $form, ?array $packing = null): ?array
    {
        return $this->queue->lower($command, $form, $packing);
    }

    public function append(array $command, CommandReach $how, float $grow = 0.0, ?array $pieces = null, bool $restless = false, ?array $reads = null): void
    {
        $this->queue->append($command, $how->value, $grow, $pieces, $restless, $reads);
    }

    public function drawn(int $surfaceWidth, int $surfaceHeight, int $unitWidth, int $unitHeight, int $limitWidth, int $limitHeight, array $also = []): array
    {
        return $this->queue->drawn($surfaceWidth, $surfaceHeight, $unitWidth, $unitHeight, $limitWidth, $limitHeight, $also);
    }

    public function changed(int $surfaceWidth, int $surfaceHeight, int $unitWidth, int $unitHeight, int $limitWidth, int $limitHeight, array $also = []): array
    {
        return $this->queue->changed($surfaceWidth, $surfaceHeight, $unitWidth, $unitHeight, $limitWidth, $limitHeight, $also);
    }
}
