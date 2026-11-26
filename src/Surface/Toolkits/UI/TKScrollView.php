<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;
use Surface\Contracts\Toolkits\Mail\Views\ViewScrolled;
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

    private bool $watching_scroll = false;

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

    public function scrollPosition(): ScrollPosition
    {
        $this->guardLive();

        return $this->nativeScrollPosition();
    }

    public function watchScroll(bool $on = true): static
    {
        $this->guardLive();
        if ($on !== $this->watching_scroll) {
            $this->watching_scroll = $on;
            $this->applyWatchScroll($on);
        }

        return $this;
    }

    public function isWatchingScroll(): bool
    {
        return $this->watching_scroll;
    }

    /**
     * The native scrolled, or what it scrolls changed size: ViewScrolled, the latest per
     * pump, while watching. Call it from the toolkit's own scroll callback.
     */
    protected function scrolled(): void
    {
        if ($this->watching_scroll && ! $this->isRemoved()) {
            $this->window()->postLatest('view.scrolled.'.$this->uuid(), new ViewScrolled($this->window()->name(), $this->path(), $this->uuid(), $this->nativeScrollPosition()));
        }
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

    /**
     * The visible rect against the scrolled container, from the toolkit's own offsets and sizes.
     */
    abstract protected function nativeScrollPosition(): ScrollPosition;

    /**
     * Watching began or ended. For a toolkit that must start something to hear a scroll;
     * one whose callback is always there calls scrolled() from it and needs nothing here.
     */
    protected function applyWatchScroll(bool $on): void {}
}
