<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

use Surface\Contracts\Toolkits\Mail\PrimitiveMail;

/** The user moved keyboard focus away from this primitive's native. */
readonly class FocusLost extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "view.focus-lost.{$this->window}.{$this->path}";
    }
}
