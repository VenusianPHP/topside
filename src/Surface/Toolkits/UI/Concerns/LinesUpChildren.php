<?php

namespace Surface\Toolkits\UI\Concerns;

use InvalidArgumentException;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;

/**
 * What a column, a row and a grid share: children one after another, a gap between them, and
 * padding round them all, each side its own. Which axis is the only difference, and that is
 * the toolkit's.
 */
trait LinesUpChildren
{
    private int $spacing;

    private Insets $padding;

    /**
     * The spacing and padding are known before the native is built, so it is built with them.
     *
     * @param int $spacing points between one child and the next
     * @param Insets|int $padding points between the edge and the children: an int is every side
     * @throws ToolkitException When either is negative, or the name is refused.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        int $spacing = 0,
        Insets|int $padding = 0,
    ) {
        if ($spacing < 0) {
            throw new ToolkitException("Spacing is 0 or more, got {$spacing} for '{$name}'.");
        }

        try {
            $this->padding = Insets::of($padding);
        } catch (InvalidArgumentException $e) {
            throw new ToolkitException("Padding for '{$name}': {$e->getMessage()}", previous: $e);
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

    public function padding(): Insets
    {
        return $this->padding;
    }

    /**
     * @throws ToolkitException When removed, or a side is negative or unreadable.
     */
    public function setPadding(Insets|int|string|array $padding): static
    {
        $this->guardLive();

        $this->padding = $this->insets($padding, 'padding');
        $this->applyPadding($this->padding);

        return $this;
    }

    /**
     * Set the gap between children on the native.
     */
    abstract protected function applySpacing(int $spacing): void;

    /**
     * Set the space between the edge and the children on the native, each side its own. The
     * native was built with padding() already: this is a change after that.
     */
    abstract protected function applyPadding(Insets $padding): void;
}
