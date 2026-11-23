<?php

namespace Surface\Toolkits\Managers;

use ReflectionException;
use Surface\Toolkits\Views\Sharp;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Drivers\ContentTreeDriver;
use Surface\Toolkits\Drivers\PresentationTreeDriver;
use Voyager\NutsAndBolts\Manager;

class PresentationTreeManager extends Manager
{
    /**
     * Put a view on screen: $view names a Component class or a sharp template, built through
     * the container with $props set on its public properties.
     *
     * @param array<string, mixed> $props
     * @throws ViewException When $view names no view, or a prop it does not have.
     * @throws ReflectionException
     */
    public function render(string $view, array $props = []): void
    {
        /** @var ContentTreeDriver $driver */
        $driver = $this->driver();

        $driver->render($this->resolve($view)->assign($props));
    }

    /**
     * @throws ViewException
     * @throws ReflectionException
     */
    protected function resolve(string $view): Component
    {
        $sharp = $this->vessel->make(Sharp::class);
        $sharp->refresh();

        // The class first, so a class name is never read as a template's dotted name.
        $class = class_exists($view) && is_subclass_of($view, Component::class) ? $view : $sharp->view($view);

        return $this->vessel->make($class);
    }

    public function createToolkitDriver(): PresentationTreeDriver
    {
        return new PresentationTreeDriver($this->vessel);
    }

    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return config('main.presentation.mode', 'toolkit');
    }
}
