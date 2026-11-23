<?php

namespace Surface\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\ListRow;
use Surface\Contracts\Toolkits\UI\TKListView as PrimitiveContract;
use Surface\Toolkits\ToolkitException;

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
        $this->guardLive();
        if ($height < 1) {
            throw new ToolkitException("Row height must be at least 1, got {$height}.");
        }
        $this->row_height = $height;
        $this->write(fn () => $this->applyRowHeight($height));

        return $this;
    }

    abstract protected function applyRowHeight(int $height): void;
}
