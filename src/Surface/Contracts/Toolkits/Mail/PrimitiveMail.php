<?php

namespace Surface\Contracts\Toolkits\Mail;

use Voyager\Contracts\Signals\NamedSignal;

interface PrimitiveMail extends NamedSignal
{
    /**
     * @return string the window's name
     */
    public function window(): string;

    /**
     * @return string the primitive's dotted path
     */
    public function path(): string;

    /**
     * @return string the primitive's uuid
     */
    public function uuid(): string;
}