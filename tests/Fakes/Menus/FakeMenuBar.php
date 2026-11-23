<?php

namespace Venusian\Topside\Tests\Fakes\Menus;

use LogicException;
use RuntimeException;
use Surface\Toolkits\Menus\Runtime\ToolkitMenuBar;
use Surface\Contracts\Toolkits\Menus\BarNode;
use Surface\Contracts\Toolkits\Menus\BarNodeKind;
use Surface\Contracts\Toolkits\Menus\StandardKind;

/**
 * A toolkit's bar that records each operation and keeps the tree a native would. Like a
 * native it refuses a wrong index, an unknown node and any use after release().
 */
class FakeMenuBar extends ToolkitMenuBar
{
    /** @var list<array> each operation, by label rather than uuid */
    public array $ops = [];

    /** @var array<string, BarNode> */
    public array $nodes = [];

    /** @var array<string, list<string>> uuids by parent; '' is the bar */
    public array $under = ['' => []];

    public bool $released = false;

    /** @var list<StandardKind> */
    public static array $unsupported = [];

    /** @var list<string> the windows whose bar was shown, '' for no window */
    public static array $shown = [];

    /** An insert of this label fails, as a native error would. */
    public ?string $failInsert = null;

    public function insert(?string $parent, int $index, BarNode $node): void
    {
        $this->live();
        $under = $parent ?? '';
        if ($under !== '' && ($this->node($under)->kind !== BarNodeKind::MENU)) {
            throw new LogicException('insert under a node that is no menu');
        }
        if ($node->uuid === '' || isset($this->nodes[$node->uuid])) {
            throw new LogicException('insert with no uuid of its own');
        }
        if ($index < 0 || $index > count($this->under[$under])) {
            throw new LogicException("insert at {$index}, out of range");
        }
        if (! is_null($this->failInsert) && $node->label === $this->failInsert) {
            throw new RuntimeException("native refused '{$node->label}'");
        }

        $this->nodes[$node->uuid] = $node;
        $this->under[$node->uuid] = [];
        array_splice($this->under[$under], $index, 0, [$node->uuid]);
        $this->ops[] = ['insert', $this->nameOf($parent), $index, $this->nameOf($node->uuid)];
    }

    public function remove(string $uuid): void
    {
        $this->live();
        $this->ops[] = ['remove', $this->nameOf($uuid)];
        $parent = $this->parentOf($uuid);
        array_splice($this->under[$parent], array_search($uuid, $this->under[$parent], true), 1);
        $this->drop($uuid);
    }

    public function move(string $uuid, ?string $parent, int $index): void
    {
        $this->live();
        $under = $parent ?? '';
        $from = $this->parentOf($uuid);
        if ($from !== $under || $index < 0 || $index >= count($this->under[$under])) {
            throw new LogicException("move to {$index}, out of range");
        }
        array_splice($this->under[$from], array_search($uuid, $this->under[$from], true), 1);
        array_splice($this->under[$under], $index, 0, [$uuid]);
        $this->ops[] = ['move', $this->nameOf($uuid), $index];
    }

    public function change(string $uuid, array $changes): void
    {
        $this->live();
        if ($changes === [] || array_diff(array_keys($changes), ['label', 'enabled', 'on', 'chosen', 'shortcut']) !== []) {
            throw new LogicException('change() of nothing, or of what it does not take');
        }
        $this->ops[] = ['change', $this->nameOf($uuid), $changes];
        $this->nodes[$uuid] = new BarNode(...[...get_object_vars($this->node($uuid)), ...$changes]);
    }

    public function show(): void
    {
        $this->live();
        static::$shown[] = $this->window?->name() ?? '';
    }

    public function release(): void
    {
        $this->live();
        [$this->released, $this->nodes, $this->under] = [true, [], ['' => []]];
    }

    public function supports(StandardKind $kind): bool
    {
        return ! in_array($kind, static::$unsupported, true);
    }

    /** The uuid of the node at labels joined by '/': 'File/Export/PDF'. */
    public function uuid(string $at): string
    {
        $parent = '';
        foreach (explode('/', $at) as $name) {
            $parent = array_find($this->under[$parent], fn (string $uuid): bool => $this->nameOf($uuid) === $name)
                ?? throw new LogicException("Nothing at '{$at}'.");
        }

        return $parent;
    }

    public function at(string $at): BarNode
    {
        return $this->node($this->uuid($at));
    }

    /** The user chooses it: the native's own signal. */
    public function choose(string $at, bool|string|null $value = null): void
    {
        $this->activated($this->uuid($at), $value);
    }

    /** @return list<string> the titles of the bar's menus */
    public function titles(): array
    {
        return array_map($this->nameOf(...), $this->under['']);
    }

    /** @return list<string> what is in the menu at $at */
    public function in(string $at): array
    {
        return array_map($this->nameOf(...), $this->under[$this->uuid($at)]);
    }

    /** The whole tree, with no uuid: what is on screen. */
    public function tree(string $parent = ''): array
    {
        return array_map(function (string $uuid): array {
            $node = get_object_vars($this->nodes[$uuid]);
            unset($node['uuid']);

            return ['node' => $node, 'under' => $this->tree($uuid)];
        }, $this->under[$parent]);
    }

    protected function nameOf(?string $uuid): string
    {
        if (is_null($uuid)) {
            return '';
        }
        $node = $this->node($uuid);

        return match ($node->kind) {
            BarNodeKind::SEPARATOR => '-',
            BarNodeKind::CHOICE => implode('|', $node->options),
            default => $node->label,
        };
    }

    protected function node(string $uuid): BarNode
    {
        return $this->nodes[$uuid] ?? throw new LogicException('an unknown node');
    }

    protected function parentOf(string $uuid): string
    {
        foreach ($this->under as $parent => $children) {
            if (in_array($uuid, $children, true)) {
                return (string) $parent;
            }
        }

        throw new LogicException('a node with no parent');
    }

    protected function drop(string $uuid): void
    {
        foreach ($this->under[$uuid] as $child) {
            $this->drop($child);
        }
        unset($this->nodes[$uuid], $this->under[$uuid]);
    }

    protected function live(): void
    {
        if ($this->released) {
            throw new LogicException('used after release()');
        }
    }
}
