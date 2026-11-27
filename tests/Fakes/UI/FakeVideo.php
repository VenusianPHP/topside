<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKVideo;

/**
 * A video view with a pretend player: what it was told is kept, and start()/stop()/finish()/
 * fail() play the player's reports. FakeVideo::$player = false acts as a toolkit with none.
 */
class FakeVideo extends TKVideo
{
    use FakeNative;

    public static bool $player = true;

    public float $at = 0.0;

    public ?float $length = null;

    public function supported(): bool
    {
        return self::$player;
    }

    /** The player started. */
    public function start(): void
    {
        $this->playing();
    }

    /** The player stopped short of the end. */
    public function stop(): void
    {
        $this->paused();
    }

    /** The player reached the end. */
    public function finish(): void
    {
        $this->ended();
    }

    /** The player could not play its source. */
    public function fail(string $reason): void
    {
        $this->failed($reason);
    }

    protected function applySource(?string $url): void
    {
        $this->record('applySource', $url);
    }

    protected function applyPlay(): void
    {
        $this->record('applyPlay');
    }

    protected function applyPause(): void
    {
        $this->record('applyPause');
    }

    protected function applySeek(float $seconds): void
    {
        $this->record('applySeek', $seconds);
        $this->at = $seconds;
    }

    protected function applyMuted(bool $muted): void
    {
        $this->record('applyMuted', $muted);
    }

    protected function applyLoop(bool $loop): void
    {
        $this->record('applyLoop', $loop);
    }

    protected function applyControls(bool $controls): void
    {
        $this->record('applyControls', $controls);
    }

    protected function readPosition(): float
    {
        return $this->at;
    }

    protected function readDuration(): ?float
    {
        return $this->length;
    }
}
