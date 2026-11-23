<?php

namespace Surface\Toolkits\Menus;

use Closure;
use Surface\Contracts\Toolkits\Menus\MenuNode;

/**
 * A whole menu bar, as a value. Written in a config file, returned from a method, or made
 * from another bar: every method here that changes something returns a copy.
 *
 *     Menus::profile('app')
 *         ->extend('file', fn (Menu $file) => $file->append(Item::make('Export', id: 'export')))
 *         ->with(Menu::make('Format', ...))
 *         ->without('help')
 */
final readonly class MenuBar
{
    use CopiesItself;

    /**
     * @param list<Menu> $menus
     * @throws MenuException When something in it is not a Menu, two menus have one key, or
     *         there are two application menus.
     */
    public function __construct(
        public array $menus = [],
    ) {
        $keys = [];
        $application = false;

        foreach ($menus as $menu) {
            if (! $menu instanceof Menu) {
                throw new MenuException('A menu bar holds menus, not '.get_debug_type($menu).': put it in a Menu.');
            }

            if ($menu->application) {
                if ($application) {
                    throw new MenuException('A menu bar has one application menu, not two.');
                }
                $application = true;
            }

            $key = $menu->key();
            if (isset($keys[$key])) {
                throw new MenuException("Two menus of the bar have the key '{$key}'. Give one of them another id.");
            }
            $keys[$key] = true;
        }
    }

    public static function make(Menu ...$menus): self
    {
        return new self(array_values($menus));
    }

    /**
     * With $menus: one whose key the bar already has takes that menu's place, the others go
     * at the end.
     */
    public function with(Menu ...$menus): self
    {
        $all = $this->menus;
        foreach ($menus as $menu) {
            $at = $this->indexOf($menu->key());
            is_null($at) ? $all[] = $menu : $all[$at] = $menu;
        }

        return new self($all);
    }

    /**
     * Without the menus that have these keys. A key no menu has is ignored.
     */
    public function without(string ...$keys): self
    {
        return new self(array_values(array_filter(
            $this->menus,
            static fn (Menu $menu): bool => ! in_array($menu->key(), $keys, true),
        )));
    }

    /**
     * With the menu whose key is $key changed by $change.
     *
     * @param Closure(Menu): Menu $change
     * @throws MenuException When no menu has the key.
     */
    public function extend(string $key, Closure $change): self
    {
        $at = $this->requireIndexOf($key);
        $menus = $this->menus;
        $menus[$at] = $change($menus[$at]);

        return new self($menus);
    }

    /**
     * With the node at $path changed by $change. The path is the one its mail is named with.
     *
     *     $bar->item('file.save', fn (Item $save) => $save->enabled($this->dirty))
     *
     * @param Closure(Node): MenuNode $change
     * @throws MenuException When no node has the path.
     */
    public function item(string $path, Closure $change): self
    {
        $paths = $this->paths();
        if (! isset($paths[$path])) {
            throw new MenuException("The menu bar has nothing at '{$path}'. It has: ".implode(', ', array_keys($paths)).'.');
        }

        $ids = explode('.', $path);
        $menus = $this->menus;
        foreach ($menus as $at => $menu) {
            if ($menu->id === $ids[0]) {
                $menus[$at] = $menu->changing(array_slice($ids, 1), $change);
            }
        }

        return new self($menus);
    }

    /**
     * With $menu just before the menu whose key is $key.
     *
     * @throws MenuException When no menu has the key.
     */
    public function before(string $key, Menu $menu): self
    {
        return $this->splice($this->requireIndexOf($key), $menu);
    }

    /**
     * With $menu just after the menu whose key is $key.
     *
     * @throws MenuException When no menu has the key.
     */
    public function after(string $key, Menu $menu): self
    {
        return $this->splice($this->requireIndexOf($key) + 1, $menu);
    }

    /**
     * The menu whose key is $key; null when none is.
     */
    public function menu(string $key): ?Menu
    {
        $at = $this->indexOf($key);

        return is_null($at) ? null : $this->menus[$at];
    }

    /**
     * The node at $path; null when none is.
     */
    public function find(string $path): ?Node
    {
        return $this->paths()[$path] ?? null;
    }

    /**
     * The application menu, when the bar has one.
     */
    public function application(): ?Menu
    {
        return array_find($this->menus, static fn (Menu $menu): bool => $menu->application);
    }

    /**
     * Every node of every menu and submenu, in the order shown. The bar's own menus are not
     * in it.
     *
     * @return list<Node>
     */
    public function all(): array
    {
        return array_merge(...array_map(static fn (Menu $menu): array => $menu->all(), $this->menus), ...[[]]);
    }

    /**
     * Every node with an id, by the path its mail is named with: 'file.export.pdf'.
     *
     * @return array<string, Node>
     */
    public function paths(): array
    {
        return array_merge([], ...array_map(static fn (Menu $menu): array => $menu->paths(), $this->menus));
    }

    /**
     * The nodes that run a callback. A bar from a config file must have none: a callback
     * cannot be cached.
     *
     * @return list<Node>
     */
    public function callbacks(): array
    {
        return array_values(array_filter($this->all(), static fn (Node $node): bool => ! is_null($node->callback())));
    }

    /**
     * The bar as a platform shows it: without what only the other platform has, and with
     * every shortcut checked against the others.
     *
     * @throws MenuException When two of its nodes have the same shortcut on the platform.
     */
    public function on(bool $mac): self
    {
        $menus = [];
        foreach ($this->menus as $menu) {
            $menus[] = $menu->on($mac);
        }

        $bar = new self($menus);
        $bar->checkShortcuts($mac);

        return $bar;
    }

    /**
     * @throws MenuException When two nodes have the same shortcut on the platform.
     */
    private function checkShortcuts(bool $mac): void
    {
        /** @var list<array{Shortcut, Node}> $taken */
        $taken = [];

        foreach ($this->all() as $node) {
            $shortcut = match (true) {
                $node instanceof StandardItem => $node->shortcutOn($mac),
                $node instanceof Item, $node instanceof Toggle => $node->shortcut,
                default => null,
            };
            if (is_null($shortcut)) {
                continue;
            }

            foreach ($taken as [$other, $holder]) {
                if ($shortcut->same($other, $mac)) {
                    throw new MenuException(ucfirst($holder->describe())." and {$node->describe()} have the same shortcut, {$shortcut->describe($mac)}.");
                }
            }
            $taken[] = [$shortcut, $node];
        }
    }

    private function indexOf(?string $key): ?int
    {
        foreach ($this->menus as $index => $menu) {
            if ($menu->key() === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @throws MenuException When no menu has the key.
     */
    private function requireIndexOf(string $key): int
    {
        return $this->indexOf($key) ?? throw new MenuException("The menu bar has no menu with the key '{$key}'. It has: ".implode(', ', array_map(
            static fn (Menu $menu): string => (string) $menu->key(),
            $this->menus,
        )).'.');
    }

    private function splice(int $at, Menu $menu): self
    {
        $menus = $this->menus;
        array_splice($menus, $at, 0, [$menu]);

        return new self($menus);
    }
}
