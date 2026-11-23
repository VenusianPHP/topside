<?php

namespace Surface\Toolkits\Menus\Runtime;

/**
 * What the user last chose on a toggle or choice. It is shown while the definition still says
 * what it said then; a definition with another value takes over.
 *
 * @internal
 */
final readonly class MarkState
{
    /**
     * @param bool|string $base what the definition said when the user chose
     * @param bool $kept whether it outlasts the node's absence: an item with an id and no
     *        callback, which nothing controls
     */
    public function __construct(
        public bool|string $base,
        public bool|string $value,
        public bool $kept,
    ) {}
}
