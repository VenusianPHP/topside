<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

use Surface\Contracts\Toolkits\Mail\PrimitiveMail;
use DateTimeImmutable;

readonly class DateChanged extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly DateTimeImmutable $date,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.date-changed.{$this->window}.{$this->path}";
    }
}
