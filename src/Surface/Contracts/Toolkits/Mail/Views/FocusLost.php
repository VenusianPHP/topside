<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

use Surface\Contracts\Toolkits\Mail\PrimitiveMail;

/** Keyboard focus moved away from this primitive's native, by any of the moves FocusGained names. */
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
