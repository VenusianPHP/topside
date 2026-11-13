<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

use Surface\Contracts\Toolkits\Mail\PrimitiveMail;

/** The user moved keyboard focus to this primitive's native: a click, Tab, or the toolkit's own move. */
readonly class FocusGained extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "view.focus-gained.{$this->window}.{$this->path}";
    }
}
