<?php

namespace Surface\Toolkits\Menus\Runtime;

use Surface\Contracts\Toolkits\Menus\BarNode;

/**
 * One native node: what it was last made to match.
 *
 * @internal The MenuMounter's record.
 */
final class MountedNode
{
    /** @var list<MountedNode> in the order shown */
    public array $children = [];

    public function __construct(
        public ?PlannedNode $planned = null,
        public ?BarNode $node = null,
    ) {}
}
