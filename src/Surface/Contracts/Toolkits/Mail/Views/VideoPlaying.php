<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

/**
 * A video view's player started playing, whoever asked it to.
 */
readonly class VideoPlaying extends PrimitiveEventOccurred
{
    public function name(): string
    {
        return "view.video.playing.{$this->window}.{$this->path}";
    }
}
