<?php

namespace Surface\Toolkits\Menus;

use Surface\Toolkits\Menus\Radios;
use Surface\Toolkits\Menus\MenuItem;
use Surface\Toolkits\Menus\Accelerator;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\Menus\MenuProfile as MenuContract;

readonly class MenuProfile implements MenuContract
{
    /** The standard accelerator of each edit role. */
    public const array EDIT_KEYS = [
        'undo' => 'Primary+Z',
        'redo' => 'Primary+Shift+Z',
        'cut' => 'Primary+X',
        'copy' => 'Primary+C',
        'paste' => 'Primary+V',
        'select-all' => 'Primary+A',
    ];

    /**
     * @param string $name
     * @param list<MenuItem> $folders top-level bar entries, every one a folder
     */
    private function __construct(
        public string $name,
        public array $folders,
    ) {}

    /**
     * @param string $name
     * @param array $nodes
     * @return self
     * @throws ToolkitRuntimeException
     */
    public static function parse(string $name, array $nodes): self
    {
        $folders = [];
        if (is_array($nodes[array_key_first($nodes) ?? 0] ?? null) && array_key_exists('enabled', $nodes[array_key_first($nodes)]))
        {
            $label = $nodes[array_key_first($nodes)]['label'] ?? '';
            throw new ToolkitRuntimeException("Menu profile '{$name}': the first folder '{$label}' takes no enabled: on macOS it is the application menu.");
        }
        foreach ($nodes as $node)
        {
            $folder = MenuItem::fromArray($node, '');
            if (! $folder->isFolder())
            {
                throw new ToolkitRuntimeException("Menu profile '{$name}': top-level entry '{$folder->label}' must be a folder.");
            }
            $folders[] = $folder;
        }

        try
        {
            MenuItem::nodesOf($folders);
            MenuItem::radioGroupsOf($folders);
        }
        catch (ToolkitRuntimeException $e)
        {
            throw new ToolkitRuntimeException("Menu profile '{$name}': {$e->getMessage()}", 0, $e);
        }
        $profile = new self($name, $folders);
        $profile->guardAccelerators();

        return $profile;
    }

    /**
     * Every item and every folder, by id, in tree order. Separators are not nodes.
     *
     * @return array<string, MenuItem>
     */
    public function nodes(): array
    {
        return MenuItem::nodesOf($this->folders);
    }

    /**
     * Two nodes with one id, or two items with the same accelerator on macOS or on other platforms,
     * throw. Each edit role holds its standard accelerator.
     *
     * @throws ToolkitRuntimeException
     */
    private function guardAccelerators(): void
    {
        $taken = [];
        foreach ($this->nodes() as $item)
        {
            $keys = $item->keys ?? (is_null($item->role) || ! $item->role->isEdit() ? null : Accelerator::parse(self::EDIT_KEYS[$item->role->value]));
            if (is_null($keys))
            {
                continue;
            }
            foreach ($taken as [$label, $other])
            {
                if ($keys->sameAs($other, true) || $keys->sameAs($other, false))
                {
                    throw new ToolkitRuntimeException("Menu profile '{$this->name}': item '{$item->label}' has the same accelerator as '{$label}'.");
                }
            }
            $taken[] = [$item->label, $keys];
        }
    }

    /**
     * Every non-folder, non-separator item, keyed by id. @return array<string, MenuItem>
     *
     * @return array
     */
    public function items(): array
    {
        $out = [];
        $walk = function (array $items) use (&$walk, &$out): void
        {
            foreach ($items as $item)
            {
                if ($item->isFolder())
                {
                    $walk($item->items);
                }
                elseif (! $item->separator)
                {
                    $out[$item->id] = $item;
                }
            }
        };

        $walk($this->folders);

        return $out;
    }

    /**
     * The radio groups and the item each starts on: group → item id.
     *
     * @return array<string, string>
     */
    public function radios(): array
    {
        return Radios::onOf($this->items(), MenuItem::radioGroupsOf($this->folders));
    }

    /**
     * A radio group's item ids, in tree order.
     *
     * @return list<string>
     * @throws ToolkitRuntimeException When no radio group has the name.
     */
    public function radioItems(string $group): array
    {
        return MenuItem::radioGroupsOf($this->folders)[$group] ?? throw new ToolkitRuntimeException("No radio group '{$group}' in the menu bar.");
    }

    /**
     * Initial toggle states.
     *
     * @return array<string, bool>
     */
    public function toggles(): array
    {
        return array_map(fn (MenuItem $i): bool => $i->on, array_filter($this->items(), fn (MenuItem $i): bool => $i->toggle));
    }
}