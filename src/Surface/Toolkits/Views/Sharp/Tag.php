<?php

namespace Surface\Toolkits\Views\Sharp;

use ReflectionClass;

/**
 * What a tag turned out to be.
 *
 * @internal
 */
final readonly class Tag
{
    public function __construct(
        public string $name,
        public TagKind $kind,
        /** The element's or component's class; for a template, its path under resources/. */
        public string $target,
        /** What its attributes are checked against. */
        public ReflectionClass $declares,
        /** The file that declares it. */
        public string $file,
    ) {}

    /**
     * Where the tag led, as the cache records it.
     */
    public function resolution(): string
    {
        return "{$this->kind->value}:{$this->target}";
    }
}
