<?php

namespace Surface\Contracts\Toolkits\Mail\Windows;

use Voyager\Contracts\Signals\NamedSignal;

interface WindowMail extends NamedSignal
{
    public function window(): string;
}