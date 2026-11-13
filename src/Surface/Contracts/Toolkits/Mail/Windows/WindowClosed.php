<?php

namespace Surface\Contracts\Toolkits\Mail\Windows;

readonly class WindowClosed extends WindowEventOccurred implements WindowMail
{

    /**
     * @return string
     */
    public function name(): string
    {
        return "window.closed.{$this->window}";
    }
}