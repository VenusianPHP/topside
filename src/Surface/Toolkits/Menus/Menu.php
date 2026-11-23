<?php

namespace Surface\Toolkits\Menus;

use Closure;
use Surface\Contracts\Toolkits\Menus\MenuNode;

/**
 * A menu of the bar, or a submenu inside another menu.
 *
 *     Menu::make('File', 'file',                  // title, then the id
 *         Item::make('Open', id: 'open')->shortcut(Shortcut::primary('O')),
 *         Separator::make(),
 *         Menu::make('Recent', 'recent', ...),
 *     )
 *
 * Its key is its id, or its title when it has none: what MenuBar::extend() and the like find
 * it by. A menu that holds an item with an id needs an id, since the item's mail is named by
 * the path of ids down to it.
 */
final readonly class Menu extends Node
{
    /** The application menu's id. */
    public const string APPLICATION = 'app';

    /**
     * @param list<MenuNode> $nodes
     * @param bool $application the menu macOS shows first, under the application's name
     * @throws MenuException When it has no title, it needs an id and has none, two nodes share
     *         an id or a key, or a submenu is an application menu.
     */
    public function __construct(
        public string $title,
        public array $nodes = [],
        public ?string $id = null,
        public bool $application = false,
        public bool $enabled = true,
    ) {
        if ($title === '' && ! $application) {
            throw new MenuException('A menu needs a title.');
        }
        self::checkId($id, $this->describe());

        $name = ucfirst($this->describe());
        $ids = [];
        $keys = [];

        foreach ($nodes as $node) {
            if (! $node instanceof Node) {
                throw new MenuException("{$name} holds something that is not a menu node: ".get_debug_type($node).'.');
            }
            if ($node instanceof self && $node->application) {
                throw new MenuException("{$name}: the application menu belongs to the bar, not inside a menu.");
            }

            $named = self::idOf($node);
            if (! is_null($named)) {
                if (isset($ids[$named])) {
                    throw new MenuException("{$name}: {$ids[$named]->describe()} and {$node->describe()} share the id '{$named}'.");
                }
                $ids[$named] = $node;
            }

            $key = $node->key();
            if (is_null($key)) {
                continue;
            }
            if (isset($keys[$key])) {
                throw new MenuException("{$name}: {$keys[$key]->describe()} and {$node->describe()} cannot be told apart. Give one of them an id.");
            }
            $keys[$key] = $node;
        }

        if (is_null($id)) {
            $mails = array_find($this->all(), static fn (Node $node): bool => ! is_null($node->mailId()));
            if (! is_null($mails)) {
                throw new MenuException("{$name} holds {$mails->describe()}, which has the id '{$mails->mailId()}', so the menu needs an id too, as its second argument: Menu::make('{$title}', '<id>', ...).");
            }
        }
    }

    /**
     * A menu titled $title: then its id when it has one, then its nodes. The id cannot be
     * named, because PHP takes no named argument before a list.
     *
     * @throws MenuException For a named argument, or a string after the id.
     */
    public static function make(string $title, MenuNode|string ...$idAndNodes): self
    {
        $id = null;
        $nodes = [];

        foreach ($idAndNodes as $at => $given) {
            if (is_string($at)) {
                throw new MenuException("Menu::make() takes no '{$at}:'. A menu's id is its second argument: Menu::make('File', 'file', ...).");
            }
            if (! is_string($given)) {
                $nodes[] = $given;
            } elseif ($at === 0) {
                $id = $given;
            } else {
                throw new MenuException("Menu::make() takes a title, an id and then nodes, not the string '{$given}' among them.");
            }
        }

        return new self($title, $nodes, $id);
    }

    /**
     * The application menu, id 'app': the one macOS shows first, under the application's
     * name. Off macOS it is an ordinary first menu titled with the application's name.
     */
    public static function application(MenuNode ...$nodes): self
    {
        return new self('', array_values($nodes), self::APPLICATION, application: true);
    }

    public function titled(string $title): self
    {
        return $this->with(['title' => $title]);
    }

    public function enabled(bool $enabled = true): self
    {
        return $this->with(['enabled' => $enabled]);
    }

    /**
     * With $nodes after the ones it has.
     */
    public function append(MenuNode ...$nodes): self
    {
        return $this->with(['nodes' => [...$this->nodes, ...array_values($nodes)]]);
    }

    /**
     * With $nodes before the ones it has.
     */
    public function prepend(MenuNode ...$nodes): self
    {
        return $this->with(['nodes' => [...array_values($nodes), ...$this->nodes]]);
    }

    /**
     * With $nodes just before the node whose key is $key.
     *
     * @throws MenuException When no node has the key.
     */
    public function before(string $key, MenuNode ...$nodes): self
    {
        return $this->splice($this->indexOf($key), 0, array_values($nodes));
    }

    /**
     * With $nodes just after the node whose key is $key.
     *
     * @throws MenuException When no node has the key.
     */
    public function after(string $key, MenuNode ...$nodes): self
    {
        return $this->splice($this->indexOf($key) + 1, 0, array_values($nodes));
    }

    /**
     * Without the nodes that have these keys. A key no node has is ignored.
     */
    public function without(string ...$keys): self
    {
        return $this->with(['nodes' => array_values(array_filter(
            $this->nodes,
            static fn (Node $node): bool => ! in_array($node->key(), $keys, true),
        ))]);
    }

    /**
     * With $node in place of the node whose key is $key.
     *
     * @throws MenuException When no node has the key.
     */
    public function replace(string $key, MenuNode $node): self
    {
        return $this->splice($this->indexOf($key), 1, [$node]);
    }

    /**
     * The node of this menu whose key is $key; null when none is.
     */
    public function node(string $key): ?Node
    {
        return array_find($this->nodes, static fn (Node $node): bool => $node->key() === $key);
    }

    /**
     * Every node in this menu and in its submenus, in the order shown.
     *
     * @return list<Node>
     */
    public function all(): array
    {
        $all = [];
        foreach ($this->nodes as $node) {
            $all[] = $node;
            if ($node instanceof self) {
                array_push($all, ...$node->all());
            }
        }

        return $all;
    }

    /**
     * Every node with an id in this menu and its submenus, by the path its mail is named with.
     *
     * @return array<string, Node>
     */
    public function paths(): array
    {
        if (is_null($this->id)) {
            return [];    // such a menu holds nothing with an id
        }

        $paths = [];
        foreach ($this->nodes as $node) {
            if ($node instanceof self) {
                foreach ($node->paths() as $path => $named) {
                    $paths["{$this->id}.{$path}"] = $named;
                }
            } elseif (! is_null($node->mailId())) {
                $paths["{$this->id}.{$node->mailId()}"] = $node;
            }
        }

        return $paths;
    }

    /**
     * This menu with $change applied to the node at $path, the ids below this menu's own.
     * For MenuBar::item(), which has checked that the path is there.
     *
     * @internal
     * @param list<string> $path
     * @param Closure(Node): MenuNode $change
     */
    public function changing(array $path, Closure $change): self
    {
        $nodes = $this->nodes;
        foreach ($nodes as $at => $node) {
            if (self::idOf($node) === $path[0]) {
                $nodes[$at] = $node instanceof self ? $node->changing(array_slice($path, 1), $change) : $change($node);
            }
        }

        return $this->with(['nodes' => $nodes]);
    }

    /**
     * This menu as a platform shows it: without what only the other one has, in every submenu.
     */
    public function on(bool $mac): self
    {
        $nodes = [];
        foreach ($this->nodes as $node) {
            if ($node->shownOn($mac)) {
                $nodes[] = $node instanceof self ? $node->on($mac) : $node;
            }
        }

        return $this->with(['nodes' => $nodes]);
    }

    public function key(): ?string
    {
        return $this->id ?? $this->title;
    }

    public function describe(): string
    {
        return $this->application ? 'the application menu' : "menu '{$this->title}'";
    }

    private static function idOf(Node $node): ?string
    {
        return $node instanceof self ? $node->id : $node->mailId();
    }

    /**
     * @throws MenuException When no node has the key.
     */
    private function indexOf(string $key): int
    {
        foreach ($this->nodes as $index => $node) {
            if ($node->key() === $key) {
                return $index;
            }
        }

        throw new MenuException(ucfirst($this->describe())." has nothing with the key '{$key}'. It has: ".implode(', ', array_filter(array_map(
            static fn (Node $node): ?string => $node->key(),
            $this->nodes,
        ))).'.');
    }

    /**
     * @param list<MenuNode> $replacement
     */
    private function splice(int $at, int $length, array $replacement): self
    {
        $nodes = $this->nodes;
        array_splice($nodes, $at, $length, $replacement);

        return $this->with(['nodes' => $nodes]);
    }
}
