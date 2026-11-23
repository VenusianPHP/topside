<?php

namespace Surface\Toolkits;

use ReflectionException;
use Surface\Toolkits\Views\Sharp;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Elements\ComponentElement;
use Surface\Toolkits\Managers\PresentationTreeManager;

class View
{
    /**
     * Put a view on screen in place of the one there.
     *
     * @param string $view a Component class, or a sharp template's name: 'demo.index' is resources/demo/index.sharp.php
     * @param array<string, mixed> $props set on the view's public properties before it renders
     * @throws ViewException When $view names no view, or a prop it does not have.
     * @throws ReflectionException
     */
    public static function render(string $view, array $props = []): void
    {
        app(PresentationTreeManager::class)->render($view, $props);
    }

    /**
     * A sharp template as an element, for a composed view to use:
     *
     *     View::component('components.badge', text: 'New')
     *
     * @param string $template its name, as render() takes it
     * @throws ViewException When there is no such template, or it does not compile.
     * @throws ReflectionException
     */
    public static function component(string $template, mixed ...$props): ComponentElement
    {
        return new ComponentElement(app(Sharp::class)->view($template), $props);
    }
}
