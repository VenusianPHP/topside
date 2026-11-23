<?php

namespace Surface\Toolkits\Menus\Runtime;

use Throwable;
use Surface\Toolkits\Menus\Node;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\Toggle;
use Surface\Toolkits\Menus\Choice;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\Standard;
use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\Separator;
use Surface\Toolkits\Menus\AboutPanel;
use Surface\Toolkits\Menus\StandardItem;
use Surface\Toolkits\Menus\MenuException;
use Surface\Contracts\Toolkits\Menus\StandardKind;

/**
 * A Sharp @menu block laid over the bar the view is given. A menu whose key is new goes at
 * the end; one whose key is there replaces it, or with extend changes it. Inside extend a
 * node whose id is there is changed, only in what was written; the others go at the end.
 *
 * @internal Compiled templates call it.
 */
final class MenuBlock
{
    private function __construct() {}

    /**
     * @param list<MenuTag> $tags
     * @param bool $replace the block is the whole bar
     * @throws MenuException With the template's line, when the bar cannot be what was written.
     */
    public static function lay(MenuBar $bar, array $tags, bool $replace = false): MenuBar
    {
        if ($replace) {
            $bar = MenuBar::make();
        }

        foreach ($tags as $tag) {
            $bar = self::at($tag, static function () use ($bar, $tag): MenuBar {
                $key = self::key($tag);

                return $tag->extend && ! is_null($key) && ! is_null($bar->menu($key))
                    ? $bar->extend($key, static fn (Menu $menu): Menu => self::changeMenu($menu, $tag))
                    : $bar->with(self::newMenu($tag));
            });
        }

        return $bar;
    }

    private static function changeMenu(Menu $menu, MenuTag $tag): Menu
    {
        $nodes = $menu->nodes;

        foreach ($tag->children as $child) {
            $nodes = self::at($child, static function () use ($nodes, $child): array {
                $at = self::there($nodes, $child);
                if (is_null($at)) {
                    $nodes[] = self::fresh($child);
                } else {
                    $nodes[$at] = self::change($nodes[$at], $child);
                }

                return $nodes;
            });
        }

        $written = array_intersect_key($tag->attributes, ['title' => true, 'enabled' => true]);

        return self::at($tag, static fn (): Menu => new Menu(...[...get_object_vars($menu), ...$written, 'nodes' => $nodes]));
    }

    /**
     * The node a tag inside extend changes: the one with its id, or for a menu that says
     * extend, the one with its key.
     *
     * @param list<Node> $nodes
     */
    private static function there(array $nodes, MenuTag $tag): ?int
    {
        $id = $tag->attributes['id'] ?? null;
        $key = $tag->extend ? self::key($tag) : null;

        foreach ($nodes as $at => $node) {
            $named = $node instanceof Menu ? $node->id : $node->mailId();
            if ((! is_null($id) && $named === (string) $id) || (! is_null($key) && $node instanceof Menu && $node->key() === $key)) {
                return $at;
            }
        }

        return null;
    }

    private static function change(Node $node, MenuTag $tag): Node
    {
        $is = match ($tag->name) {
            'Menu', 'EditMenu', 'WindowMenu' => Menu::class,
            'Item' => Item::class,
            'Toggle' => Toggle::class,
            'Choice' => Choice::class,
            'Separator' => Separator::class,
            default => StandardItem::class,
        };
        if (! $node instanceof $is) {
            throw new MenuException("<{$tag->name}> cannot change {$node->describe()}: it is another kind.");
        }

        if ($node instanceof Menu) {
            return self::changeMenu($node, $tag);
        }

        $written = self::written($tag);

        if ($node instanceof StandardItem) {
            $kind = $written['kind'] ?? $node->kind;
            if ($kind !== $node->kind || (in_array($tag->name, ['AboutPanel', 'AboutItem'], true) && $node->kind !== StandardKind::ABOUT)) {
                throw new MenuException("<{$tag->name}> cannot change {$node->describe()}: it is another kind.");
            }
            if ($tag->name === 'AboutPanel') {
                [$panel, $written] = self::panelApart($written);
                if ($panel !== []) {
                    $before = is_null($node->panel) ? [] : get_object_vars($node->panel);
                    $written['panel'] = new AboutPanel(...[...$before, ...$panel]);
                }
            }
        }

        return new ($node::class)(...[...get_object_vars($node), ...$written]);
    }

    private static function fresh(MenuTag $tag): Node
    {
        return self::at($tag, static function () use ($tag): Node {
            if (in_array($tag->name, ['Menu', 'EditMenu', 'WindowMenu'], true)) {
                return self::newMenu($tag);
            }

            $written = self::written($tag);
            $needs = match ($tag->name) {
                'Item' => ['label'],
                'Toggle' => ['label', 'on'],
                'Choice' => ['options', 'chosen'],
                'Standard' => ['kind'],
                'AboutPanel' => ['name'],
                default => [],
            };
            foreach ($needs as $need) {
                if (! array_key_exists($need, $written)) {
                    $id = isset($written['id']) ? "Nothing there has the id '{$written['id']}' to change, and a" : 'A';
                    throw new MenuException("{$id} new <{$tag->name}> needs {$need}.");
                }
            }

            switch ($tag->name) {
                case 'Item':
                    return new Item(...$written);
                case 'Toggle':
                    return new Toggle(...$written);
                case 'Choice':
                    return new Choice(...$written);
                case 'Separator':
                    return Separator::make();
                case 'Standard':
                    return new StandardItem(...$written);
                case 'AboutItem':
                    return new StandardItem(...[...$written, 'kind' => StandardKind::ABOUT]);
                default:
                    [$panel, $written] = self::panelApart($written);

                    return new StandardItem(...[...$written, 'kind' => StandardKind::ABOUT, 'panel' => new AboutPanel(...$panel)]);
            }
        });
    }

    private static function newMenu(MenuTag $tag): Menu
    {
        $written = $tag->attributes;
        $nodes = array_map(self::fresh(...), $tag->children);

        if ($tag->name !== 'Menu') {
            $menu = $tag->name === 'EditMenu' ? Standard::editMenu() : Standard::windowMenu();

            return new Menu(...[...get_object_vars($menu), ...$written]);
        }

        $application = (bool) ($written['application'] ?? false);
        $title = (string) ($written['title'] ?? '');
        $key = $written['key'] ?? null;

        if ($title === '' && ! $application) {
            throw new MenuException($tag->extend
                ? "There is no menu with the key '".self::key($tag)."' to extend, and a new one needs title."
                : 'A new <Menu> needs title.');
        }

        // A key is an id, or a title where there is no id.
        $id = $written['id'] ?? ($application ? Menu::APPLICATION : ($key === $title ? null : $key));

        return new Menu($title, $nodes, $id, $application, (bool) ($written['enabled'] ?? true));
    }

    private static function key(MenuTag $tag): ?string
    {
        $written = $tag->attributes;
        $key = match ($tag->name) {
            'EditMenu' => 'edit',
            'WindowMenu' => 'window',
            default => $written['key'] ?? $written['id']
                ?? (($written['application'] ?? false) ? Menu::APPLICATION : ($written['title'] ?? null)),
        };

        return is_null($key) ? null : (string) $key;
    }

    /**
     * @return array<string, mixed>
     */
    private static function written(MenuTag $tag): array
    {
        $written = $tag->attributes;
        if (array_key_exists('shortcut', $written)) {
            $shortcut = $written['shortcut'];
            $written['shortcut'] = match (true) {
                is_string($shortcut) => Shortcut::of($shortcut),
                // A standard item has one unless it is taken off.
                is_null($shortcut) && ! in_array($tag->name, ['Item', 'Toggle'], true) => false,
                default => $shortcut,
            };
        }

        return $written;
    }

    /**
     * @param array<string, mixed> $written
     * @return array{array<string, mixed>, array<string, mixed>} the panel's, and the rest
     */
    private static function panelApart(array $written): array
    {
        $panel = ['name' => true, 'version' => true, 'copyright' => true, 'credits' => true];

        return [array_intersect_key($written, $panel), array_diff_key($written, $panel)];
    }

    /**
     * What $do gives, or a MenuException that names the tag's line: the innermost tag's.
     */
    private static function at(MenuTag $tag, callable $do): mixed
    {
        try {
            return $do();
        } catch (Throwable $failure) {
            if ($failure instanceof MenuException && str_starts_with($failure->getMessage(), '@menu line ')) {
                throw $failure;
            }

            throw new MenuException("@menu line {$tag->line}, <{$tag->name}>: {$failure->getMessage()}", 0, $failure);
        }
    }
}
