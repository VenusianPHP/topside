<?php

namespace Surface\Contracts\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKPrimitive;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

/**
 * A row of joined segments, one selected at a time. Posts SelectionChanged.
 */
interface TKSegmentedControl extends TKPrimitive
{
    /**
     * @return list<string>
     */
    public function segments(): array;

    /**
     * Replace every segment. The selection stays when it is still in range; else the first
     * segment is selected (-1 when there are none).
     * @param list<string> $segments
     * @return $this
     * @throws ToolkitRuntimeException When the segments are not a list of strings.
     */
    public function setSegments(array $segments): static;

    /**
     * @return $this
     * @throws ToolkitRuntimeException When the index is outside the segments.
     */
    public function setSegment(int $index, string $label): static;

    /**
     * @return int -1 exactly when there are no segments
     */
    public function selected(): int;

    /**
     * Posts nothing: the app changed it, it knows.
     * @return $this
     * @throws ToolkitRuntimeException When the index is outside the segments.
     */
    public function select(int $index): static;
}
