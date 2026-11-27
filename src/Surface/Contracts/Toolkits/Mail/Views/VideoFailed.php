<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

/**
 * A video view could not play its source, or has no player to play one with (its supported()
 * is false).
 */
readonly class VideoFailed extends PrimitiveEventOccurred
{
    /**
     * @param string|null $source what it was asked to play
     * @param string|null $reason the player's own words, when it gives any
     */
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public ?string $source = null,
        public ?string $reason = null,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.video.failed.{$this->window}.{$this->path}";
    }
}
