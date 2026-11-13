<?php

namespace Surface\Toolkits\Support;

use Closure;
use ReflectionException;
use Surface\Toolkits\Navigation\Navigator;

class ViewActions
{
    /** @var array<string, Closure|array|string> widget uuid => handler */
    protected array $handlers = [];

    /** @var array<string, Closure> widget uuid => handler of its edits (TextChanged, SelectionChanged) */
    protected array $changes = [];

    public function bind(string $uuid, Closure|array|string $handler): void
    {
        $this->handlers[$uuid] = $handler;
    }

    /**
     * A handler for what the user does to a widget's value as it happens (typing, choosing), kept
     * apart from bind(): an input's submit handler must not run on every keystroke.
     */
    public function onChange(string $uuid, Closure $handler): void
    {
        $this->changes[$uuid] = $handler;
    }

    public function fireChange(string $uuid, object $mail): void
    {
        ($this->changes[$uuid] ?? null)?->__invoke($mail);
    }

    /** Run the handler bound to $uuid, if any.
     * @throws ReflectionException
     */
    public function fire(string $uuid, object $mail): void
    {
        $handler = $this->handlers[$uuid] ?? null;

        match (true) {
            is_null($handler) => null,
            is_string($handler) => app(Navigator::class)->go($handler),
            is_array($handler) => app($handler[0])->{$handler[1]}($mail),
            default => $handler($mail),
        };
    }
}
