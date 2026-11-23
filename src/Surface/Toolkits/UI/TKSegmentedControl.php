<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Toolkits\UI\Concerns\IgnoresItsOwnWrites;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;
use Surface\Contracts\Toolkits\UI\TKSegmentedControl as PrimitiveContract;

/**
 * One choice from a few, all of them showing. The selection is -1 exactly when there are no
 * segments.
 *
 * Toolkits build this from several natives and signal while those are rebuilt, so every
 * write here is inside write().
 */
abstract class TKSegmentedControl extends TKPrimitive implements PrimitiveContract
{
    use IgnoresItsOwnWrites;

    /** @var list<string> */
    private array $segments;

    private int $selected;

    /**
     * @param list<string> $segments
     * @param int $selected ignored when there are no segments
     * @throws ToolkitException When the segments are not a list of strings, or $selected is out of range.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        array $segments = [],
        int $selected = 0,
    ) {
        $this->segments = self::guardSegments($segments, $name);
        $this->selected = $segments === [] ? -1 : $this->guardIndex($selected, $name);

        parent::__construct($name, $window, $parent, $placement);
    }

    public function segments(): array
    {
        return $this->segments;
    }

    /**
     * The selection is kept when still in range, else the first segment (-1 when empty).
     *
     * @throws ToolkitException When removed, or the segments are not a list of strings.
     */
    public function setSegments(array $segments): static
    {
        $this->guardLive();

        $this->segments = self::guardSegments($segments, $this->path());
        $this->selected = match (true) {
            $segments === [] => -1,
            $this->selected >= 0 && $this->selected < count($segments) => $this->selected,
            default => 0,
        };
        $this->write(fn () => $this->applySegments($this->segments, $this->selected));

        return $this;
    }

    /**
     * @throws ToolkitException When removed, or the index is out of range.
     */
    public function setSegment(int $index, string $label): static
    {
        $this->guardLive();

        $this->segments[$this->guardIndex($index, $this->path())] = $label;
        $this->write(fn () => $this->applySegment($index, $label));

        return $this;
    }

    public function selected(): int
    {
        return $this->selected;
    }

    /**
     * Posts nothing: the app changed it, it knows.
     *
     * @throws ToolkitException When removed, or the index is out of range.
     */
    public function select(int $index): static
    {
        $this->guardLive();

        $this->selected = $this->guardIndex($index, $this->path());
        $this->write(fn () => $this->applySelected($index));

        return $this;
    }

    /**
     * The user chose segment $index: record it and post SelectionChanged.
     */
    protected function chosen(int $index): void
    {
        if ($index === $this->selected || $index < 0 || $index >= count($this->segments) || ! $this->hearsUser()) {
            return;
        }

        $this->selected = $index;
        $this->window()->post(new SelectionChanged($this->window()->name(), $this->path(), $this->uuid(), $index, $this->segments[$index]));
    }

    /**
     * @return list<string>
     * @throws ToolkitException
     */
    private static function guardSegments(array $segments, string $where): array
    {
        if (! array_is_list($segments) || array_filter($segments, static fn (mixed $segment): bool => ! is_string($segment)) !== []) {
            throw new ToolkitException("A segmented control's segments are a list of strings, for '{$where}'.");
        }

        return $segments;
    }

    /**
     * @throws ToolkitException
     */
    private function guardIndex(int $index, string $where): int
    {
        $count = count($this->segments);
        if ($index < 0 || $index >= $count) {
            throw new ToolkitException("Segment {$index} is out of range for '{$where}', which has {$count}.");
        }

        return $index;
    }

    /**
     * Replace the native's segments, then select $selected among them (-1: none, no segments).
     *
     * @param list<string> $segments
     */
    abstract protected function applySegments(array $segments, int $selected): void;

    abstract protected function applySegment(int $index, string $label): void;

    abstract protected function applySelected(int $index): void;
}
