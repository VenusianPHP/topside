<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;

/**
 * A container that lays its children out itself: a column, a row, a grid, a fixed, the pages
 * of a tab view, the panes of a split. What they share
 * beyond any container is that a child has a position among the others, and can be moved
 * to another one (in all but a fixed).
 */
abstract class TKGroup extends TKPrimitiveGroup
{
    protected function keepsOrder(): bool
    {
        return true;
    }

    /**
     * Move a child to position $index among the children, where 0 is first. Reached from
     * the child's own moveBefore(), moveAfter() and moveTo(), where keepsOrder() allows them.
     *
     * @throws ToolkitException When the index is out of range.
     */
    protected function arrange(TKPrimitive $child, int $index): void
    {
        $last = count($this->children) - 1;

        if ($index < 0 || $index > $last) {
            throw new ToolkitException("'{$child->path()}' moves to position 0 to {$last}, got {$index}.");
        }

        $order = array_values(array_filter($this->children, fn (TKPrimitive $other): bool => $other !== $child));
        array_splice($order, $index, 0, [$child]);

        $this->children = [];
        foreach ($order as $ordered) {
            $this->children[$ordered->name()] = $ordered;
        }

        $this->reorderNative($child, $index);
    }

    /**
     * Move $child's native to display position $index among the children.
     */
    abstract protected function reorderNative(TKPrimitive $child, int $index): void;
}
