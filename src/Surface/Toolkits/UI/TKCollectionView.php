<?php

namespace Surface\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\CollectionItem;
use Surface\Contracts\Toolkits\UI\TKCollectionView as PrimitiveContract;
use Surface\Toolkits\ToolkitException;

abstract class TKCollectionView extends TKItemView implements PrimitiveContract
{
    protected int $cell_width = 200;

    protected int $cell_height = 150;

    protected static function itemClass(): string
    {
        return CollectionItem::class;
    }

    public function cellSize(): array
    {
        return [$this->cell_width, $this->cell_height];
    }

    public function setCellSize(int $width, int $height): static
    {
        $this->guardLive();
        if ($width < 1 || $height < 1) {
            throw new ToolkitException("Cell size must be at least 1x1, got {$width}x{$height}.");
        }
        [$this->cell_width, $this->cell_height] = [$width, $height];
        $this->write(fn () => $this->applyCellSize($width, $height));

        return $this;
    }

    abstract protected function applyCellSize(int $width, int $height): void;
}
