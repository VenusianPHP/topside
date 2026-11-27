<?php

namespace Surface\Toolkits\Navigation;

use Closure;
use BadMethodCallException;
use InvalidArgumentException;
use Surface\Toolkits\Menus\MenuBar;

/**
 * A route the navigator opens. middleware() is a method of a route and, called statically, of
 * a group of routes (as a model's query methods are, on the model and on its class).
 *
 * @method static ToolkitPageRouteGroup middleware(string|Closure|array ...$middleware) Routes declared behind $middleware: call group() on the answer.
 */
class ToolkitPageRoute
{
    /** @var array<string, static> */
    protected static array $routes = [];

    /** @var list<list<string|Closure>> the middleware of each group being declared, outermost first */
    protected static array $groups = [];

    protected string|MenuBar|Closure|null $menu = null;

    /** @var list<string|Closure> */
    protected array $middleware = [];

    /**
     * @param array{0: class-string, 1: string} $action
     */
    final protected function __construct(
        protected readonly array $action,
    ) {}

    public static function get(string $name, array $action): static
    {
        $route = static::$routes[$name] = new static($action);
        $route->middleware = array_merge(...static::$groups);

        return $route;
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

    /**
     * The middleware the navigator runs round this route's controller, in order: its groups'
     * first, outermost first, then its own.
     *
     * @return list<string|Closure>
     */
    public function middlewareGiven(): array
    {
        return $this->middleware;
    }

    /**
     * $route->middleware(...) adds to the route's own middleware; Route::middleware(...)
     * starts a group.
     */
    public function __call(string $method, array $arguments): static
    {
        if ($method !== 'middleware') {
            throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
        }

        array_push($this->middleware, ...static::flatten($arguments));

        return $this;
    }

    public static function __callStatic(string $method, array $arguments): ToolkitPageRouteGroup
    {
        if ($method !== 'middleware') {
            throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
        }

        return new ToolkitPageRouteGroup()->middleware(...$arguments);
    }

    /**
     * Run $routes with $middleware given to every route it declares.
     *
     * @internal ToolkitPageRouteGroup::group()
     *
     * @param list<string|Closure> $middleware
     */
    public static function grouped(array $middleware, Closure $routes): void
    {
        static::$groups[] = $middleware;
        try {
            $routes();
        } finally {
            array_pop(static::$groups);
        }
    }

    /**
     * Middleware given one by one, in lists, or both, as one list.
     *
     * @internal
     *
     * @param array<string|Closure|list<string|Closure>> $given
     * @return list<string|Closure>
     */
    public static function flatten(array $given): array
    {
        $middleware = [];
        foreach ($given as $each) {
            array_push($middleware, ...(is_array($each) ? array_values($each) : [$each]));
        }

        return $middleware;
    }
}
