<?php

namespace Surface\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\ListRow;
use Surface\Contracts\Toolkits\Primitives\TKListView as PrimitiveContract;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

abstract class TKListView extends TKItemView implements PrimitiveContract
{
    protected int $row_height = 64;

    protected static function itemClass(): string
    {
        return ListRow::class;
    }

    public function rowHeight(): int
    {
        return $this->row_height;
    }

    public function setRowHeight(int $height): static
    {
        $this->live();
        if ($height < 1) {
            throw new ToolkitRuntimeException("Row height must be at least 1, got {$height}.");
        }
        $this->row_height = $height;
        $this->applyRowHeight($height);

        return $this;
    }

    abstract protected function applyRowHeight(int $height): void;
}
