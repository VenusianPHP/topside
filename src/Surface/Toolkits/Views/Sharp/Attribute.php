<?php

namespace Surface\Toolkits\Views\Sharp;

/**
 * An attribute of a tag: name="text", :name="expression", @name="handler", or the name alone.
 *
 * @internal
 */
final readonly class Attribute
{
    /**
     * @param string $prefix '', ':' or '@'
     * @param string $name camelCase
     * @param string|null $value null when the name stands alone
     */
    public function __construct(
        public string $prefix,
        public string $name,
        public ?string $value,
        public int $line,
    ) {}
}
