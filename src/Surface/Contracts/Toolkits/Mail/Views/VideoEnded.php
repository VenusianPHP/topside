<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

use Surface\Contracts\Toolkits\Mail\PrimitiveMail;
readonly class VideoEnded extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "view.video-ended.{$this->window}.{$this->path}";
    }
}
