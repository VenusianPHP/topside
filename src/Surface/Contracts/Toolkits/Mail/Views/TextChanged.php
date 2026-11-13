<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

use Surface\Contracts\Toolkits\Mail\PrimitiveMail;
readonly class TextChanged extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly string $value,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.text-changed.{$this->window}.{$this->path}";
    }
}
