<?php

namespace Surface\Toolkits\Views\Sharp;

use ReflectionClass;

/**
 * A template's @script as PHP loaded it: the trait its compiled class uses.
 *
 * @internal
 */
final readonly class Script
{
    public function __construct(
        public Source $source,
        /** @var class-string */
        public string $trait,
        /** The file the trait is in. */
        public string $path,
    ) {}

    public function reflect(): ReflectionClass
    {
        return new ReflectionClass($this->trait);
    }
}
