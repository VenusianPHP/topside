<?php

namespace Surface\Toolkits\Navigation;

use Closure;

/**
 * Routes declared together behind the same middleware: Route::middleware([...])->group(...).
 */
final class ToolkitPageRouteGroup
{
    /** @var list<string|Closure> */
    protected array $middleware = [];

    /**
     * Run $middleware round every route the group declares, after the middleware already
     * given, before the route's own.
     *
     * @param string|Closure|list<string|Closure> ...$middleware
     */
    public function middleware(string|Closure|array ...$middleware): static
    {
        array_push($this->middleware, ...ToolkitPageRoute::flatten($middleware));

        return $this;
    }

    /**
     * Declare the group's routes: every route $routes declares runs the group's middleware.
     */
    public function group(Closure $routes): void
    {
        ToolkitPageRoute::grouped($this->middleware, $routes);
    }
}
