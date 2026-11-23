<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\UI\Concerns\LinesUpChildren;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\UI\TKGrid as PrimitiveContract;
use Surface\Contracts\Toolkits\UI\TKPrimitive as ChildContract;

/**
 * Children in cells, one in each. at() sets the cell the next creation call takes; without
 * it a child starts a new row at the bottom. Children trade cells in one arrangeCells().
 *
 * A child's placement() is the cell it was created at; where it is now is cellOf().
 */
abstract class TKGrid extends TKGroup implements PrimitiveContract
{
    use LinesUpChildren;

    private ?Placement $pending = null;

    /** @var array<string, Placement> by the child's uuid */
    private array $cells = [];

    /**
     * @throws ToolkitException When removed, row or column is negative, a span is below 1, or a cell is taken.
     */
    public function at(int $row, int $column, int $rowSpan = 1, int $columnSpan = 1): static
    {
        $this->guardLive();

        if ($row < 0 || $column < 0 || $rowSpan < 1 || $columnSpan < 1) {
            throw new ToolkitException("A cell is at 0 or more and spans 1 or more, got {$row},{$column} spanning {$rowSpan}x{$columnSpan}.");
        }
        $cell = Placement::cell($row, $column, $rowSpan, $columnSpan);
        $this->guardApart([...$this->cells, 'new' => $cell]);
        $this->pending = $cell;

        return $this;
    }

    /**
     * @throws ToolkitException When the child is not here.
     */
    public function cellOf(ChildContract $child): Placement
    {
        return $this->cells[$child->uuid()]
            ?? throw new ToolkitException("'{$child->path()}' is not in '{$this->path()}'.");
    }

    /**
     * @param list<array{ChildContract, Placement}> $cells
     * @throws ToolkitException When removed, a child is not here, or two would share a cell; nothing changes then.
     */
    public function arrangeCells(array $cells): static
    {
        $this->guardLive();

        $after = $this->cells;
        foreach ($cells as [$child, $cell]) {
            $this->cellOf($child);
            if (is_null($cell->row) || is_null($cell->column)) {
                throw new ToolkitException("'{$child->path()}' goes in a cell: Placement::cell(row, column).");
            }
            $after[$child->uuid()] = $cell;
        }

        if ($after != $this->cells) {
            $this->guardApart($after);
            $this->cells = $after;
            $this->applyCells();
        }

        return $this;
    }

    /**
     * The order is for moveTo() alone: cells say where children are.
     */
    protected function reorderNative(TKPrimitive $child, int $index): void {}

    protected function nextPlacement(): Placement
    {
        $cell = $this->pending;
        $this->pending = null;
        if (! is_null($cell)) {
            return $cell;
        }

        $below = 0;
        foreach ($this->cells as $taken) {
            $below = max($below, $taken->row + $taken->rowSpan);
        }

        return Placement::cell($below, 0);
    }

    protected function adopt(TKPrimitive $child): void
    {
        $this->cells[$child->uuid()] = $child->placement();

        parent::adopt($child);
    }

    protected function release(TKPrimitive $child): void
    {
        parent::release($child);

        unset($this->cells[$child->uuid()]);
    }

    /**
     * @param array<string, Placement> $cells
     * @throws ToolkitException When two cover one cell.
     */
    private function guardApart(array $cells): void
    {
        $taken = [];
        foreach ($cells as $cell) {
            for ($row = $cell->row; $row < $cell->row + $cell->rowSpan; $row++) {
                for ($column = $cell->column; $column < $cell->column + $cell->columnSpan; $column++) {
                    if (isset($taken["{$row},{$column}"])) {
                        throw new ToolkitException("Cell {$row},{$column} of '{$this->path()}' would hold two children.");
                    }
                    $taken["{$row},{$column}"] = true;
                }
            }
        }
    }

    /**
     * Put every child at its cellOf(). A child being added gets there in attachNative().
     */
    abstract protected function applyCells(): void;
}
