<?php

namespace Surface\Contracts\Toolkits\Mail\Menus;

use Voyager\Contracts\Signals\NamedSignal;

interface MenuMail extends NamedSignal
{
    /**
     * @return string|null the window that was focused; null for the bar shown when none is
     */
    public function window(): ?string;

    /**
     * @return string the ids of the menus down to the item, then the item's: 'file.save'
     */
    public function path(): string;
}
