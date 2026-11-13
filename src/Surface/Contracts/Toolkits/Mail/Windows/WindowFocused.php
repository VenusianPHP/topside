<?php

namespace Surface\Contracts\Toolkits\Mail\Windows;

readonly class WindowFocused extends WindowEventOccurred implements WindowMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "window.focused.{$this->window}";
    }
}