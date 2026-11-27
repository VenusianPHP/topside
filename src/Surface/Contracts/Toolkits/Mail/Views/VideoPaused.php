<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

/**
 * A video view's player stopped playing without reaching the end: paused, or stopped to load more.
 */
readonly class VideoPaused extends PrimitiveEventOccurred
{
    public function name(): string
    {
        return "view.video.paused.{$this->window}.{$this->path}";
    }
}
