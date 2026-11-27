<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\VideoEnded;
use Surface\Contracts\Toolkits\Mail\Views\VideoFailed;
use Surface\Contracts\Toolkits\Mail\Views\VideoPaused;
use Surface\Contracts\Toolkits\Mail\Views\VideoPlaying;
use Surface\Contracts\Toolkits\UI\TKVideo as PrimitiveContract;

/**
 * A video view. The toolkit's player holds the media: position() and duration() read it. The
 * toolkit tells this one what the player did by calling playing(), paused(), ended() or
 * failed(); isPlaying() is the last of those reports.
 *
 * The toolkit is given a source as a URL: an absolute file path arrives as its file:// URL.
 * Muting, looping and the controls are kept here and handed over when set, so a toolkit
 * applies them to each media it loads.
 *
 * A toolkit with no player answers supported() with false: a source then posts VideoFailed at
 * once and nothing reaches the toolkit.
 */
abstract class TKVideo extends TKPrimitive implements PrimitiveContract
{
    public const string NO_PLAYER = 'This toolkit has no media player.';

    protected ?string $source = null;

    protected bool $playing = false;

    protected bool $muted = false;

    protected bool $looping = false;

    protected bool $controls = false;

    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function source(): ?string
    {
        return $this->source;
    }

    /**
     * @throws ToolkitException When removed, or the source is neither an absolute path nor an
     *     absolute URL.
     */
    public function setSource(?string $source): static
    {
        $this->guardLive();

        $url = is_null($source) ? null : self::urlOf($source, $this->path());
        $this->source = $source;
        $this->playing = false;

        if (! $this->supported()) {
            if (! is_null($source)) {
                $this->failed(self::NO_PLAYER);
            }

            return $this;
        }

        $this->applySource($url);

        return $this;
    }

    public function play(): static
    {
        $this->guardLive();

        if ($this->supported() && ! is_null($this->source)) {
            $this->applyPlay();
        }

        return $this;
    }

    public function pause(): static
    {
        $this->guardLive();

        if ($this->supported() && ! is_null($this->source)) {
            $this->applyPause();
        }

        return $this;
    }

    /**
     * @throws ToolkitException When removed, or $seconds is negative.
     */
    public function seek(float $seconds): static
    {
        $this->guardLive();

        if ($seconds < 0) {
            throw new ToolkitException("A video seeks to 0 seconds or later, got {$seconds} for '{$this->path()}'.");
        }

        if ($this->supported() && ! is_null($this->source)) {
            $this->applySeek($seconds);
        }

        return $this;
    }

    public function position(): float
    {
        return $this->supported() && ! is_null($this->source) && ! $this->isRemoved() ? $this->readPosition() : 0.0;
    }

    public function duration(): ?float
    {
        return $this->supported() && ! is_null($this->source) && ! $this->isRemoved() ? $this->readDuration() : null;
    }

    public function isPlaying(): bool
    {
        return $this->playing;
    }

    public function setMuted(bool $muted): static
    {
        $this->guardLive();

        if ($muted !== $this->muted) {
            $this->muted = $muted;
            if ($this->supported()) {
                $this->applyMuted($muted);
            }
        }

        return $this;
    }

    public function isMuted(): bool
    {
        return $this->muted;
    }

    public function setLoop(bool $loop): static
    {
        $this->guardLive();

        if ($loop !== $this->looping) {
            $this->looping = $loop;
            if ($this->supported()) {
                $this->applyLoop($loop);
            }
        }

        return $this;
    }

    public function isLooping(): bool
    {
        return $this->looping;
    }

    public function setControls(bool $controls): static
    {
        $this->guardLive();

        if ($controls !== $this->controls) {
            $this->controls = $controls;
            if ($this->supported()) {
                $this->applyControls($controls);
            }
        }

        return $this;
    }

    public function hasControls(): bool
    {
        return $this->controls;
    }

    public function supported(): bool
    {
        return true;
    }

    /**
     * The player started: post VideoPlaying, once per start. Nothing from a view that is going.
     */
    protected function playing(): void
    {
        if ($this->isRemoved() || $this->playing) {
            return;
        }

        $this->playing = true;
        $this->window()->post(new VideoPlaying($this->window()->name(), $this->path(), $this->uuid()));
    }

    /**
     * The player stopped short of the end: post VideoPaused, once per stop. Nothing from a
     * view that is going.
     */
    protected function paused(): void
    {
        if ($this->isRemoved() || ! $this->playing) {
            return;
        }

        $this->playing = false;
        $this->window()->post(new VideoPaused($this->window()->name(), $this->path(), $this->uuid()));
    }

    /**
     * The player reached the end of the media: post VideoEnded. Nothing from a view that is
     * going.
     */
    protected function ended(): void
    {
        if ($this->isRemoved()) {
            return;
        }

        $this->playing = false;
        $this->window()->post(new VideoEnded($this->window()->name(), $this->path(), $this->uuid()));
    }

    /**
     * The player could not play the source: post VideoFailed. Nothing from a view that is going.
     */
    protected function failed(?string $reason): void
    {
        if ($this->isRemoved()) {
            return;
        }

        $this->playing = false;
        $this->window()->post(new VideoFailed($this->window()->name(), $this->path(), $this->uuid(), $this->source, $reason));
    }

    /**
     * $source as the URL the toolkit is given: an absolute URL as it is, an absolute path as
     * its file:// URL.
     *
     * @throws ToolkitException When it is neither.
     */
    private static function urlOf(string $source, string $where): string
    {
        if (str_starts_with($source, '/')) {
            return 'file://'.implode('/', array_map(rawurlencode(...), explode('/', $source)));
        }

        if (! preg_match('/^[a-z][a-z0-9+.-]*:[^\s\x00-\x1f]+$/iD', $source)) {
            throw new ToolkitException("A video's source is an absolute path or URL (/..., https://..., file://...), got '{$source}' for '{$where}'.");
        }

        return $source;
    }

    /**
     * Load the media at $url (null: unload), paused at its start, with the muting, looping
     * and controls set here.
     */
    abstract protected function applySource(?string $url): void;

    abstract protected function applyPlay(): void;

    abstract protected function applyPause(): void;

    abstract protected function applySeek(float $seconds): void;

    abstract protected function applyMuted(bool $muted): void;

    abstract protected function applyLoop(bool $loop): void;

    abstract protected function applyControls(bool $controls): void;

    abstract protected function readPosition(): float;

    abstract protected function readDuration(): ?float;
}
