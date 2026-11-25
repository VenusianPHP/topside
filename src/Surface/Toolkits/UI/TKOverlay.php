<?php

namespace Surface\Toolkits\UI;

use InvalidArgumentException;
use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\UI\TKOverlay as PrimitiveContract;

/**
 * Children on top of each other, in display order from the back. A child reorders itself
 * (TKPrimitive::moveBefore/moveAfter/moveTo), which lands in TKGroup::arrange(), and so
 * changes which child is drawn above which.
 *
 * The toolkit places each child by its align() and margin, and sizes the overlay from the
 * children sizesOverlay() names: every child but one that fills both ways.
 */
abstract class TKOverlay extends TKGroup implements PrimitiveContract
{
    private Insets $padding;

    /**
     * @param Insets|int $padding points between the edge and the children: an int is every side
     * @throws ToolkitException When the padding has a negative side, or the name is refused.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        Insets|int $padding = 0,
    ) {
        try {
            $this->padding = Insets::of($padding);
        } catch (InvalidArgumentException $e) {
            throw new ToolkitException("Padding for '{$name}': {$e->getMessage()}", previous: $e);
        }

        parent::__construct($name, $window, $parent, $placement);
    }

    public function padding(): Insets
    {
        return $this->padding;
    }

    /**
     * Changes in place; the overlay is not built again.
     *
     * @throws ToolkitException When removed, or a side is negative or cannot be read.
     */
    public function setPadding(Insets|int|string|array $padding): static
    {
        $this->guardLive();

        try {
            $this->padding = Insets::of($padding);
        } catch (InvalidArgumentException $e) {
            throw new ToolkitException("Padding for '{$this->path()}': {$e->getMessage()}", previous: $e);
        }

        $this->applyPadding($this->padding);

        return $this;
    }

    /**
     * Whether $child counts toward the overlay's natural size: every child but one that
     * fills both ways, which takes the overlay's size instead of giving it one.
     */
    protected function sizesOverlay(TKPrimitive $child): bool
    {
        [$horizontal, $vertical] = $child->fills();

        return ! ($horizontal && $vertical);
    }

    /**
     * It takes spare room when it fills, or a child that sizes it does. A child that fills
     * both ways only takes the overlay's own size, so it never makes the overlay grow: a
     * picture covering a 320-point card leaves the card 320 points in its column.
     */
    protected function expands(bool $horizontal): bool
    {
        if ($this->fills()[$horizontal ? 0 : 1]) {
            return true;
        }
        foreach ($this->children() as $child) {
            if (! $child->isRemoved() && $child->isVisible() && $this->sizesOverlay($child) && $child->expands($horizontal)) {
                return true;
            }
        }

        return false;
    }

    abstract protected function applyPadding(Insets $padding): void;
}
