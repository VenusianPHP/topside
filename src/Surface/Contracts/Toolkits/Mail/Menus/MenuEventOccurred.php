<?php

namespace Surface\Contracts\Toolkits\Mail\Menus;

abstract readonly class MenuEventOccurred implements MenuMail
{
    public function __construct(
        public ?string $window,
        public string $path,
    ) {}

    abstract public function name(): string;

    public function window(): ?string
    {
        return $this->window;
    }

    public function path(): string
    {
        return $this->path;
    }
}
