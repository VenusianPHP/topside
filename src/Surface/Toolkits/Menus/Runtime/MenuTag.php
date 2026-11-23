<?php

namespace Surface\Toolkits\Menus\Runtime;

/**
 * One tag of a Sharp @menu block as a render left it: what was written, and nothing else.
 *
 * @internal Compiled templates make these.
 */
final readonly class MenuTag
{
    /**
     * @param string $name the tag's: Menu, Item, Toggle, ...
     * @param array<string, mixed> $attributes what was written, by the definition's argument
     * @param list<MenuTag> $children
     */
    public function __construct(
        public int $line,
        public string $name,
        public array $attributes = [],
        public array $children = [],
        public bool $extend = false,
    ) {}
}
