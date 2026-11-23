<?php

namespace Surface\Toolkits\Menus\Runtime;

use Closure;
use Surface\Toolkits\Menus\Node;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\Toggle;
use Surface\Toolkits\Menus\Choice;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\Separator;
use Surface\Toolkits\Menus\StandardItem;
use Surface\Toolkits\Menus\MenuException;
use Surface\Contracts\Toolkits\Menus\BarNode;
use Surface\Contracts\Toolkits\Menus\BarNodeKind;

/**
 * A definition as one platform shows it: what only the other has is left out, shortcuts are
 * checked, the application menu comes first, and labels, shortcuts, enablement and the
 * user's marks are worked out. It touches no toolkit.
 *
 * @internal
 */
final readonly class MenuPlanner
{
    /**
     * @param Closure(StandardItem): bool $supports whether the toolkit can do the item's kind
     */
    public function __construct(
        private bool $mac,
        private string $app,
        private Closure $supports,
    ) {}

    /**
     * @param MenuBar|null $bar null for a window with no bar
     * @param array<string, MarkState> $marks
     * @return list<PlannedNode>
     * @throws MenuException When two items have one shortcut on the platform.
     */
    public function plan(?MenuBar $bar, array $marks): array
    {
        if (is_null($bar)) {
            return [];
        }

        $bar = $bar->on($this->mac);
        $application = $bar->application() ?? ($this->mac ? Menu::application() : null);
        $menus = array_values(array_filter($bar->menus, static fn (Menu $menu): bool => ! $menu->application));
        if (! is_null($application)) {
            array_unshift($menus, $application);
        }

        return $this->nodes($menus, $marks, [], null, true);
    }

    /**
     * @param list<Node> $nodes
     * @param array<string, MarkState> $marks
     * @param list<string> $above the keys down to these nodes
     * @param string|null $path the ids down to these nodes
     * @return list<PlannedNode>
     */
    private function nodes(array $nodes, array $marks, array $above, ?string $path, bool $enabled): array
    {
        $planned = [];
        $lines = 0;

        foreach ($nodes as $node) {
            $key = $node instanceof Separator ? 'line:'.($lines++) : 'node:'.$node->key();
            $keys = [...$above, $key];
            $at = implode("\0", $keys);

            if ($node instanceof Menu) {
                $open = $enabled && $node->enabled;
                $below = is_null($node->id) ? null : (is_null($path) ? $node->id : "{$path}.{$node->id}");
                $planned[] = new PlannedNode($key, $at, new BarNode(
                    BarNodeKind::MENU,
                    label: $node->title !== '' ? $node->title : $this->app,
                    enabled: $open,
                    application: $node->application,
                ), $node, children: $this->nodes($node->nodes, $marks, $keys, $below, $open));
                continue;
            }

            $named = is_null($node->mailId()) ? null : "{$path}.{$node->mailId()}";
            $planned[] = new PlannedNode($key, $at, $this->shown($node, $enabled, $marks[$at] ?? null), $node, $named);
        }

        return $planned;
    }

    private function shown(Node $node, bool $enabled, ?MarkState $mark): BarNode
    {
        return match (true) {
            $node instanceof Item => new BarNode(
                BarNodeKind::ITEM,
                label: $node->label,
                enabled: $enabled && $node->enabled,
                shortcut: $node->shortcut,
            ),
            $node instanceof Toggle => new BarNode(
                BarNodeKind::TOGGLE,
                label: $node->label,
                enabled: $enabled && $node->enabled,
                on: ! is_null($mark) && $mark->base === $node->on ? (bool) $mark->value : $node->on,
                shortcut: $node->shortcut,
            ),
            $node instanceof Choice => new BarNode(
                BarNodeKind::CHOICE,
                enabled: $enabled && $node->enabled,
                chosen: ! is_null($mark) && $mark->base === $node->chosen && array_key_exists($mark->value, $node->options)
                    ? (string) $mark->value
                    : $node->chosen,
                options: $node->options,
            ),
            $node instanceof StandardItem => new BarNode(
                BarNodeKind::STANDARD,
                label: $node->labelOn($this->mac, $this->app),
                enabled: $enabled && $node->enabled && ($this->supports)($node),
                shortcut: $node->shortcutOn($this->mac),
                standard: $node->kind,
                panel: $node->isReplaced() ? null : $node->panel,
                replaced: $node->isReplaced(),
            ),
            $node instanceof Separator => new BarNode(BarNodeKind::SEPARATOR),
            default => throw new MenuException("A bar cannot show {$node->describe()}."),
        };
    }
}
