<?php

namespace Surface\VelvetGE\Recording;

use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Recording\Queue;
use Surface\NutsAndBolts\Affine;

/** VelvetGE's queue in PHP: the frame's commands in an array, frames compared by Boxes. Needs nothing. */
final class PHPLandQueue implements Queue
{
    /** @var list<array<int, mixed>>|null The open frame's commands, or the open layer's; null between frames. */
    private ?array $recording = null;

    private ?Affine $matrix = null;

    private ?Region $clip = null;

    /** @var list<array{Affine, Region}> */
    private array $stack = [];

    /** @var list<array{list<array<int, mixed>>, Region, list<array{Affine, Region}>}> What each entered layer set aside: the commands, the clip and the pushes around it. */
    private array $around = [];

    /** @var list<array<int, mixed>>|null The last finished frame. */
    private ?array $last = null;

    /** @var list<array<int, mixed>>|null The frame finished before it. */
    private ?array $before = null;

    public function begin(Region $surface): void
    {
        $this->recording = [];
        $this->matrix = Affine::identity();
        $this->clip = $surface;
        $this->stack = [];
        $this->around = [];
    }

    public function recording(): bool
    {
        return ! is_null($this->recording);
    }

    public function abandon(): void
    {
        $this->recording = null;
        $this->matrix = null;
        $this->clip = null;
        $this->stack = [];
        $this->around = [];
    }

    public function finish(): array
    {
        $this->before = $this->last;
        $this->last = $this->recording;
        $this->abandon();

        return $this->last;
    }

    public function push(): void
    {
        $this->stack[] = [$this->matrix, $this->clip];
    }

    public function pop(): void
    {
        [$this->matrix, $this->clip] = array_pop($this->stack);
    }

    public function depth(): int
    {
        return count($this->stack);
    }

    public function compose(Affine $matrix): void
    {
        $this->matrix = $this->matrix->multiply($matrix);
    }

    public function matrix(): Affine
    {
        return $this->matrix;
    }

    public function clip(Region $clip): void
    {
        $this->clip = $clip;
    }

    public function clipRegion(): Region
    {
        return $this->clip;
    }

    public function append(array $command): void
    {
        $this->recording[] = $command;
    }

    public function enter(Region $clip): void
    {
        $this->around[] = [$this->recording, $this->clip, $this->stack];
        $this->recording = [];
        $this->clip = $clip;
        $this->stack = [];
    }

    public function leave(): array
    {
        $children = $this->recording;
        [$this->recording, $this->clip, $this->stack] = array_pop($this->around);

        return $children;
    }

    public function drawn(Region $surface, DamageGranularity $granularity, array $also = []): array
    {
        return Boxes::merge([...Boxes::drawn($this->last ?? [], $surface), ...$also], $granularity);
    }

    public function changed(Region $surface, DamageGranularity $granularity, array $also = []): array
    {
        $after = $this->last ?? [];

        return Boxes::withBackdrops(
            Boxes::merge([...Boxes::changed($this->before ?? [], $after, $surface, $granularity), ...$also], $granularity),
            $after, $surface, $granularity,
        );
    }
}
