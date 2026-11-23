<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\TKScrollView as PrimitiveContract;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup as GroupContract;

/**
 * A window onto one container that may be larger than it. Up and down unless told; it
 * always scrolls one way at least, as Raven's must.
 */
abstract class TKScrollView extends TKPrimitiveGroup implements PrimitiveContract
{
    private bool $scroll_horizontal = false;

    private bool $scroll_vertical = true;

    /**
     * @throws ToolkitException When removed, or neither is asked for.
     */
    public function setScrollbars(bool $horizontal, bool $vertical): static
    {
        $this->guardLive();

        if (! $horizontal && ! $vertical) {
            throw new ToolkitException("'{$this->path()}' scrolls across, down or both: it cannot scroll neither way.");
        }

        [$this->scroll_horizontal, $this->scroll_vertical] = [$horizontal, $vertical];
        $this->applyScrollbars($horizontal, $vertical);

        return $this;
    }

    public function scrollsAcross(): bool
    {
        return $this->scroll_horizontal;
    }

    public function scrollsDown(): bool
    {
        return $this->scroll_vertical;
    }

    public function content(): ?GroupContract
    {
        return $this->children()[0] ?? null;
    }

    /**
     * @throws ToolkitException For a second child, or one that is no container.
     */
    protected function create(string $contract, string $name, mixed ...$with): TKPrimitive
    {
        if ($this->children !== [] || ! is_subclass_of($contract, GroupContract::class)) {
            throw new ToolkitException("'{$this->path()}' scrolls one container: put '{$name}' inside a column or row in it.");
        }

        return parent::create($contract, $name, ...$with);
    }

    abstract protected function applyScrollbars(bool $horizontal, bool $vertical): void;
}
