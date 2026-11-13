<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

readonly class ButtonClicked extends PrimitiveEventOccurred
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "view.clicked.{$this->window}.{$this->path}";
    }
}