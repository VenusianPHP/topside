<?php

namespace Surface\Contracts\Rendering\Recording;

use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\NutsAndBolts\Affine;

/**
 * Where a frame's commands are held while it is recorded, and where two frames are compared: what
 * an extension, or a library, brings to recording. VelvetGE's recorder turns every draw call into
 * commands (Recorder lists them) and keeps them, the transform and the clip here; it asks nothing
 * else of a queue, so a queue of these methods is a whole recorder.
 *
 * A queue checks nothing a recorder has checked: it is not asked to pop without a push, to append
 * outside a frame, or to leave a layer it did not enter.
 *
 * The regions a queue answers are its own arithmetic only in how fast it works them out: every
 * queue answers the same regions for the same frames.
 */
interface Queue
{
    /** Open a frame: no commands, the identity transform, $surface as the clip, nothing pushed. */
    public function begin(Region $surface): void;

    public function recording(): bool;

    /** Drop the open frame, and every layer entered in it. */
    public function abandon(): void;

    /**
     * Close the open frame and answer its commands. It becomes the last frame; the frame that was
     * the last becomes the one drawn() and changed() compare it with.
     *
     * @return list<array<int, mixed>>
     */
    public function finish(): array;

    /** Keep the transform and the clip. */
    public function push(): void;

    /** Bring back the transform and the clip of the last push(). */
    public function pop(): void;

    /** How many push() calls wait for their pop() in the frame, or the layer, being recorded. */
    public function depth(): int;

    /** The transform becomes the current one times $matrix: $matrix applies first. */
    public function compose(Affine $matrix): void;

    public function matrix(): Affine;

    /** @param Region $clip Inside the surface; it may be empty. */
    public function clip(Region $clip): void;

    public function clipRegion(): Region;

    /**
     * @param  array<int, mixed>  $command  One of Recorder's commands, whole: its last entry is its clip.
     */
    public function append(array $command): void;

    /**
     * Start a layer's children: commands are appended to a list of their own, $clip is the clip,
     * nothing is pushed. The transform stays.
     */
    public function enter(Region $clip): void;

    /**
     * End a layer's children and answer them. The commands, the clip and the pushes of before
     * enter() come back; the transform stays as the children left it.
     *
     * @return list<array<int, mixed>>
     */
    public function leave(): array;

    /**
     * Recorder::drawn(), of the last frame.
     *
     * @param  list<Region>  $also
     * @return list<Region>
     */
    public function drawn(Region $surface, DamageGranularity $granularity, array $also = []): array;

    /**
     * Recorder::changed(), of the last frame against the one before it.
     *
     * @param  list<Region>  $also
     * @return list<Region>
     */
    public function changed(Region $surface, DamageGranularity $granularity, array $also = []): array;
}
