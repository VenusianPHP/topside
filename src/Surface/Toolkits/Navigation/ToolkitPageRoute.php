<?php

namespace Surface\Toolkits\Navigation;

use Closure;
use InvalidArgumentException;
use Surface\Toolkits\Menus\MenuBar;

class ToolkitPageRoute
{
    /** @var array<string, static> */
    protected static array $routes = [];

    protected string|MenuBar|Closure|null $menu = null;

    /**
     * @param array{0: class-string, 1: string} $action
     */
    final protected function __construct(
        protected readonly array $action,
    ) {}

    public static function get(string $name, array $action): static
    {
        return static::$routes[$name] = new static($action);
    }

    public static function find(string $name): array
    {
        return static::named($name)->action;
    }

    public static function named(string $name): static
    {
        return static::$routes[$name] ?? throw new InvalidArgumentException("No route named [{$name}].");
    }

    /**
     * The window's bar while this route is open. A profile's name or a bar replaces the one
     * it was given; a closure is given it and returns the one to show.
     *
     * @param string|MenuBar|Closure(MenuBar): MenuBar $menu
     */
    public function menu(string|MenuBar|Closure $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function menuGiven(): string|MenuBar|Closure|null
    {
        return $this->menu;
    }
}
