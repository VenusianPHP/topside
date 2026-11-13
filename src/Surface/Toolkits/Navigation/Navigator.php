<?php

namespace Surface\Toolkits\Navigation;

use ReflectionException;
use Surface\Contracts\Toolkits\Mail\Views\ButtonClicked;
use Surface\Toolkits\Navigation\ToolkitPageRoute as Route;

class Navigator
{
    protected ?string $current = null;

    protected array $currentArgs = [];

    /** @var list<array{0: string, 1: array}> the routes back() returns to, oldest first */
    protected array $back = [];

    /** @var list<array{0: string, 1: array}> the routes forward() returns to, after a back() */
    protected array $forward = [];

    /**
     * Open a route. The route it leaves goes on the back history; the forward history ends.
     * Going to the route that is already open changes no history.
     * @throws ReflectionException
     */
    public function go(string $name, array $args = []): void
    {
        Route::find($name);    // an unknown route throws before the history changes

        if (! is_null($this->current) && $name !== $this->current) {
            $this->back[] = [$this->current, $this->currentArgs];
            $this->forward = [];
        }

        $this->open($name, $args);
    }

    /**
     * Open the route before this one. Does nothing when there is none.
     * @throws ReflectionException
     */
    public function back(): void
    {
        if ($entry = array_pop($this->back)) {
            $this->forward[] = [$this->current, $this->currentArgs];
            $this->open(...$entry);
        }
    }

    /**
     * Open the route back() left. Does nothing when there is none.
     * @throws ReflectionException
     */
    public function forward(): void
    {
        if ($entry = array_pop($this->forward)) {
            $this->back[] = [$this->current, $this->currentArgs];
            $this->open(...$entry);
        }
    }

    public function canGoBack(): bool
    {
        return $this->back !== [];
    }

    public function canGoForward(): bool
    {
        return $this->forward !== [];
    }

    /**
     * @throws ReflectionException
     */
    protected function open(string $name, array $args): void
    {
        [$controller, $action] = Route::find($name);
        [$this->current, $this->currentArgs] = [$name, $args];

        app($controller)->{$action}(...$args);
    }

    public function current(): ?string
    {
        return $this->current;
    }

    /**
     * Give a click to the current route's controller. The widget's name picks the
     * handler: "deck.welcome.actions.continue" calls onContinue(). A controller
     * without that handler ignores the click.
     * @throws ReflectionException
     */
    public function clicked(ButtonClicked $clicked): void
    {
        if (is_null($this->current)) {
            return;
        }

        [$controller] = Route::find($this->current);
        $widget = substr(strrchr('.'.$clicked->path, '.'), 1);
        $handler = 'on'.str_replace(['-', '_'], '', ucwords($widget, '-_'));

        $instance = app($controller);
        if (method_exists($instance, $handler)) {
            $instance->{$handler}($clicked);
        }
    }
}