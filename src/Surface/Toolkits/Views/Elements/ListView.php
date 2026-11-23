<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\ListRow;
use Surface\Contracts\Toolkits\UI\TKListView;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * A scrolled list. An item is text, or an array: title, and any of detail, image, badge,
 * action (a button's label), icons, enabled.
 *
 *     <ListView :items="$songs" @activate="fn ($song) => $this->play($song)" />
 */
class ListView extends Items
{
    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->listView($name, $this->items);
    }

    public function kind(): string
    {
        return 'listView';
    }

    public function rowHeight(int $height): static
    {
        return $this->set('rowHeight', $height);
    }

    protected function item(mixed $item): object
    {
        $row = $this->fields($item, 'title', ['detail', 'image', 'badge', 'action', 'icons', 'enabled']);

        return new ListRow(
            image: Image::path($row['image'] ?? null),
            title: (string) $row['title'],
            detail: (string) ($row['detail'] ?? ''),
            badge: isset($row['badge']) ? (string) $row['badge'] : null,
            icons: array_map(Image::path(...), array_values($row['icons'] ?? [])),
            action: $row['action'] ?? null,
            enabled: (bool) ($row['enabled'] ?? true),
        );
    }

    protected function defaults(): array
    {
        return parent::defaults() + ['rowHeight' => 64];
    }

    protected function setters(): array
    {
        return parent::setters() + ['rowHeight' => fn (TKListView $p, int $v) => $p->setRowHeight($v)];
    }
}
