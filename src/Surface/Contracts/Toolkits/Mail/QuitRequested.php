<?php

namespace Surface\Contracts\Toolkits\Mail;

use Voyager\Contracts\Signals\NamedSignal;

class QuitRequested implements NamedSignal
{
    /**
     * @param string|null $window the window whose bar held the item; null for the app-level bar
     */
    public function __construct(
        public readonly ?string $window = null
    ) {}

    public function name(): string
    {
        return 'quit.requested';
    }
}