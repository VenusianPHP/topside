<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

/**
 * A video view's player reached the end of its media. A looping one never ends.
 */
readonly class VideoEnded extends PrimitiveEventOccurred
{
    public function name(): string
    {
        return "view.video.ended.{$this->window}.{$this->path}";
    }
}
