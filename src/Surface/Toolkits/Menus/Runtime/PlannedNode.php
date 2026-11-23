<?php

namespace Surface\Toolkits\Menus\Runtime;

use Surface\Toolkits\Menus\Node;
use Surface\Contracts\Toolkits\Menus\BarNode;

/**
 * A node of a bar as it is to be shown, and what it came from.
 *
 * @internal
 */
final readonly class PlannedNode
{
    /**
     * @param string $key what matches it with the node shown before, among its siblings
     * @param string $at what matches its state, in the whole bar
     * @param string|null $path the path its mail is named with; null when it posts none
     * @param list<PlannedNode> $children
     */
    public function __construct(
        public string $key,
        public string $at,
        public BarNode $node,
        public Node $source,
        public ?string $path = null,
        public array $children = [],
    ) {}
}
