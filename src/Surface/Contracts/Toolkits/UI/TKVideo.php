<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use RuntimeException;

/**
 * Audio or video played in a view, from a file or an absolute URL (which streams). Posts
 * VideoPlaying, VideoPaused, VideoEnded and VideoFailed as the player reports them;
 * isPlaying(), position() and duration() are the player's last report.
 *
 * A toolkit with no player still makes one: supported() is false, it shows nothing, and every
 * source posts VideoFailed, so the view can offer something else (a link out).
 */
interface TKVideo extends TKPrimitive
{
    /**
     * What it plays, as it was given; null when it plays nothing.
     * @return string|null
     */
    public function source(): ?string;

    /**
     * @param string|null $source an absolute file path or an absolute URL (https, file); null unloads
     * @return $this
     * @throws RuntimeException When $source is neither.
     */
    public function setSource(?string $source): static;

    /**
     * @return $this
     */
    public function play(): static;

    /**
     * @return $this
     */
    public function pause(): static;

    /**
     * @param float $seconds from the start
     * @return $this
     * @throws RuntimeException When negative.
     */
    public function seek(float $seconds): static;

    /**
     * Seconds from the start, read from the player; 0 with nothing loaded.
     * @return float
     */
    public function position(): float;

    /**
     * Seconds, read from the player; null until the media reports it.
     * @return float|null
     */
    public function duration(): ?float;

    /**
     * @return bool
     */
    public function isPlaying(): bool;

    /**
     * @param bool $muted
     * @return $this
     */
    public function setMuted(bool $muted): static;

    /**
     * @return bool
     */
    public function isMuted(): bool;

    /**
     * @param bool $loop
     * @return $this
     */
    public function setLoop(bool $loop): static;

    /**
     * @return bool
     */
    public function isLooping(): bool;

    /**
     * Whether the toolkit shows its own transport (play and pause, the position, the sound).
     * @param bool $controls
     * @return $this
     */
    public function setControls(bool $controls): static;

    /**
     * @return bool
     */
    public function hasControls(): bool;

    /**
     * Whether this toolkit has a player to play media with.
     * @return bool
     */
    public function supported(): bool;
}
