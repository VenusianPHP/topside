<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Toolkits\UI\Concerns\IgnoresItsOwnWrites;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\SplitMoved;
use Surface\Contracts\Toolkits\UI\TKSplitView as PrimitiveContract;

/**
 * Two panes and the divider the user drags between them; a split inside a pane makes three.
 * Where the divider is, as a fraction of the length, is held here: until the native has a
 * length it has no answer of its own.
 */
abstract class TKSplitView extends TKGroup implements PrimitiveContract
{
    use IgnoresItsOwnWrites;

    /** A move this small is the toolkit rounding to a pixel. */
    private const float STILL = 0.002;

    private float $position = 0.5;

    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private readonly bool $horizontal = true,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function isHorizontal(): bool
    {
        return $this->horizontal;
    }

    public function positions(): array
    {
        return $this->hasDivider() ? [$this->position] : [];
    }

    /**
     * Taken before the panes are there too: it is where the divider will be.
     *
     * @throws ToolkitException When removed, or it is not one fraction from 0 to 1.
     */
    public function setPositions(array $positions): static
    {
        $this->guardLive();

        $at = array_is_list($positions) && count($positions) === 1 ? $positions[0] : null;
        if ((! is_float($at) && ! is_int($at)) || ! ($at >= 0 && $at <= 1)) {
            throw new ToolkitException("A split takes one position from 0 to 1 (it has one divider), for '{$this->path()}'.");
        }

        $this->position = (float) $at;
        if ($this->hasDivider()) {
            $this->write(fn () => $this->applyPosition($this->position));
        }

        return $this;
    }

    /**
     * Where the divider is, or will be.
     */
    protected function position(): float
    {
        return $this->position;
    }

    /**
     * Two panes are showing.
     */
    protected function hasDivider(): bool
    {
        return count(array_filter($this->children, static fn (TKPrimitive $pane): bool => $pane->isVisible())) === 2;
    }

    /**
     * The user dragged the divider to $position: record it and post SplitMoved, the latest per pump.
     */
    protected function dragged(float $position): void
    {
        if (! is_finite($position) || ! $this->hearsUser() || ! $this->hasDivider()) {
            return;
        }

        $position = max(0.0, min(1.0, $position));
        if (abs($position - $this->position) < self::STILL) {
            return;
        }

        $this->position = $position;
        $this->window()->postLatest('view.split-moved.'.$this->uuid(), new SplitMoved($this->window()->name(), $this->path(), $this->uuid(), [$position]));
    }

    /**
     * @throws ToolkitException For a third pane.
     */
    protected function create(string $contract, string $name, mixed ...$with): TKPrimitive
    {
        if (count($this->children) >= 2) {
            throw new ToolkitException("'{$this->path()}' has its two panes: put a split inside one for '{$name}'.");
        }

        return parent::create($contract, $name, ...$with);
    }

    protected function adopt(TKPrimitive $child): void
    {
        $this->write(function () use ($child): void {
            parent::adopt($child);
            if ($this->hasDivider()) {
                $this->applyPosition($this->position);
            }
        });
    }

    protected function release(TKPrimitive $child): void
    {
        $this->write(fn () => parent::release($child));
    }

    protected function arrange(TKPrimitive $child, int $index): void
    {
        $this->write(function () use ($child, $index): void {
            parent::arrange($child, $index);
            if ($this->hasDivider()) {
                $this->applyPosition($this->position);
            }
        });
    }

    protected function childVisibilityChanged(TKPrimitive $child): void
    {
        if ($this->hasDivider()) {
            $this->write(fn () => $this->applyPosition($this->position));
        }
    }

    /**
     * Put the divider at $position of the length, at the first layout when there is none yet.
     */
    abstract protected function applyPosition(float $position): void;
}
