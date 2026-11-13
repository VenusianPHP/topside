<?php

namespace Surface\Contracts\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

/**
 * Children in cells. at() sets the cell the next creation call uses; creating
 * without one, or into a taken cell, throws ToolkitRuntimeException.
 */
interface TKGrid extends TKPrimitiveGroup
{
    /**
     * @param int $row
     * @param int $column
     * @param int $rowSpan
     * @param int $columnSpan
     * @return $this
     * @throws ToolkitRuntimeException When row/column are negative or a span is below 1.
     */
    public function at(int $row, int $column, int $rowSpan = 1, int $columnSpan = 1): static;

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
