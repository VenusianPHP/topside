<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKVideo;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\VideoEnded;
use Surface\Contracts\Toolkits\Mail\Views\VideoFailed;
use Surface\Contracts\Toolkits\Mail\Views\VideoPaused;
use Surface\Contracts\Toolkits\Mail\Views\VideoPlaying;

/**
 * A video view: audio or video from a file or an absolute URL, which streams. A change of
 * source loads again; the same source across renders is not reloaded, and playing, pausing
 * and seeking are the player's (its own controls, or the primitive's methods).
 *
 *     <Video :source="$clip" controls @ended="next" @failed="fallBack" />
 *
 * Where the toolkit has no player it shows nothing and @failed runs.
 */
class Video extends Element
{
    final public function __construct() {}

    public static function make(): static
    {
        return new static();
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->video($name);
    }

    public function kind(): string
    {
        return 'video';
    }

    /**
     * What to play: an absolute file path or an absolute URL; null plays nothing.
     */
    public function source(?string $source): static
    {
        return $this->set('source', $source);
    }

    /**
     * Show the toolkit's own transport.
     */
    public function controls(bool $on = true): static
    {
        return $this->set('controls', $on);
    }

    public function muted(bool $on = true): static
    {
        return $this->set('muted', $on);
    }

    public function loop(bool $on = true): static
    {
        return $this->set('loop', $on);
    }

    /**
     * Run $handler when the player starts, with the VideoPlaying mail.
     *
     * @param Closure(VideoPlaying): mixed $handler
     */
    public function onPlaying(Closure $handler): static
    {
        return $this->on('playing', $handler);
    }

    /**
     * Run $handler when the player stops short of the end, with the VideoPaused mail.
     *
     * @param Closure(VideoPaused): mixed $handler
     */
    public function onPaused(Closure $handler): static
    {
        return $this->on('paused', $handler);
    }

    /**
     * Run $handler when the media plays to its end, with the VideoEnded mail.
     *
     * @param Closure(VideoEnded): mixed $handler
     */
    public function onEnded(Closure $handler): static
    {
        return $this->on('ended', $handler);
    }

    /**
     * Run $handler when the source cannot be played, or there is no player, with the
     * VideoFailed mail (its reason, when the player gives one).
     *
     * @param Closure(VideoFailed): mixed $handler
     */
    public function onFailed(Closure $handler): static
    {
        return $this->on('failed', $handler);
    }

    protected function setters(): array
    {
        // In any order: the primitive keeps the settings and loads each source with them.
        return parent::setters() + [
            'controls' => static fn (TKVideo $p, bool $on) => $p->setControls($on),
            'muted' => static fn (TKVideo $p, bool $on) => $p->setMuted($on),
            'loop' => static fn (TKVideo $p, bool $on) => $p->setLoop($on),
            'source' => static fn (TKVideo $p, ?string $source) => $p->setSource($source),
        ];
    }

    protected function defaults(): array
    {
        return parent::defaults() + ['controls' => false, 'muted' => false, 'loop' => false, 'source' => null];
    }
}
