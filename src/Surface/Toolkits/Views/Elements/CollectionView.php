<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\CollectionItem;
use Surface\Contracts\Toolkits\UI\TKCollectionView;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Pictures in as many columns as fit. An item is a caption, or an array: caption, and any
 * of image, action (a button's image), icons.
 *
 *     <CollectionView :items="$photos" cellSize="160 120" />
 */
class CollectionView extends Items
{
    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->collectionView($name, $this->items);
    }

    public function kind(): string
    {
        return 'collectionView';
    }

    public function cellSize(int $width, int $height): static
    {
        return $this->set('cellSize', [$width, $height]);
    }

    protected function item(mixed $item): object
    {
        $cell = $this->fields($item, 'caption', ['image', 'action', 'icons']);

        return new CollectionItem(
            image: Image::path($cell['image'] ?? null),
            caption: (string) $cell['caption'],
            action: Image::path($cell['action'] ?? null),
            icons: array_map(Image::path(...), array_values($cell['icons'] ?? [])),
        );
    }

    protected function defaults(): array
    {
        return parent::defaults() + ['cellSize' => [200, 150]];
    }

    protected function setters(): array
    {
        return parent::setters() + ['cellSize' => fn (TKCollectionView $p, array $v) => $p->setCellSize(...$v)];
    }
}
