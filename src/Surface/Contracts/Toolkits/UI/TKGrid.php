<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use RuntimeException;

/**
 * Children in cells. at() sets the cell the next creation call uses; creating
 * without one starts a new row at the bottom; into a taken cell throws RuntimeException.
 */
interface TKGrid extends TKPrimitiveGroup
{
    /**
     * @param int $row
     * @param int $column
     * @param int $rowSpan
     * @param int $columnSpan
     * @return $this
     * @throws RuntimeException When row/column are negative or a span is below 1.
     */
    public function at(int $row, int $column, int $rowSpan = 1, int $columnSpan = 1): static;

    /**
     * Where a child is now, after every arrangeCells().
     */
    public function cellOf(TKPrimitive $child): Placement;

    /**
     * Put children in new cells, all at once, so that they may trade places.
     *
     * @param list<array{TKPrimitive, Placement}> $cells
     * @return $this
     * @throws RuntimeException When a child is not here or two would share a cell; nothing changes then.
     */
    public function arrangeCells(array $cells): static;

    /**
     * @return int
     */
    public function spacing(): int;

    /**
     * @param int $spacing
     * @return $this
     */
    public function setSpacing(int $spacing): static;
}
