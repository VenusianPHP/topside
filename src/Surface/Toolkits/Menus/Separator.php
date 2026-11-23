<?php

namespace Surface\Toolkits\Menus;

/**
 * A line between items.
 */
final readonly class Separator extends Node
{
    public static function make(): self
    {
        return new self();
    }

    public function key(): ?string
    {
        return null;
    }

    public function describe(): string
    {
        return 'separator';
    }
}
