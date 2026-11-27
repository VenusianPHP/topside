<?php

namespace Surface\Toolkits\Navigation;

/**
 * A route being opened, as its middleware sees it: the route's name, the arguments its
 * controller is called with, and the navigator opening it. The controller is called with the
 * arguments of the visit the last middleware passes on.
 */
final readonly class Visit
{
    /**
     * @param array<array-key, mixed> $args
     */
    public function __construct(
        public string $name,
        public array $args,
        public Navigator $navigator,
    ) {}
}
