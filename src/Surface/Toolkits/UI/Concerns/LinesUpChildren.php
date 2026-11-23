<?php

namespace Surface\Toolkits\UI\Concerns;

use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;

/**
 * What a column and a row share: children one after another along one axis, a gap between
 * them, and a margin around them all. Which axis is the only difference, and that is the
 * toolkit's.
 */
trait LinesUpChildren
{
    private int $spacing;

    /**
     * The spacing and padding are known before the native is built, so it is built with them.
     *
     * @param int $spacing points between one child and the next
     * @param int $padding points between the edge and the children, on every side
     * @throws ToolkitException When either is negative, or the name is refused.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        int $spacing = 0,
        protected readonly int $padding = 0,
    ) {
        if ($spacing < 0 || $padding < 0) {
            throw new ToolkitException("Spacing and padding are 0 or more, got {$spacing} and {$padding} for '{$name}'.");
        }

        $this->spacing = $spacing;

        parent::__construct($name, $window, $parent, $placement);
    }

    public function spacing(): int
    {
        return $this->spacing;
    }

    /**
     * @throws ToolkitException When removed, or the spacing is negative.
     */
    public function setSpacing(int $spacing): static
    {
        $this->guardLive();

        if ($spacing < 0) {
            throw new ToolkitException("Spacing is 0 or more, got {$spacing} for '{$this->path()}'.");
        }

        $this->spacing = $spacing;
        $this->applySpacing($spacing);

        return $this;
    }

    /**
     * Set the gap between children on the native.
     */
    abstract protected function applySpacing(int $spacing): void;
}
