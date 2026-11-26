<?php

namespace Surface\Toolkits\UI\Concerns;

/**
 * A column or row that can give every child the same length along its axis, whatever each
 * one's own size: two cards side by side as wide as each other.
 */
trait SharesLengthEvenly
{
    private bool $homogeneous = false;

    /**
     * @throws \Surface\Toolkits\ToolkitException When removed.
     */
    public function setHomogeneous(bool $on = true): static
    {
        $this->guardLive();

        $this->homogeneous = $on;
        $this->applyHomogeneous($on);

        return $this;
    }

    public function isHomogeneous(): bool
    {
        return $this->homogeneous;
    }

    /**
     * Does nothing until an adapter overrides it: a toolkit with no native way leaves each
     * child its own length.
     */
    protected function applyHomogeneous(bool $on): void {}
}
