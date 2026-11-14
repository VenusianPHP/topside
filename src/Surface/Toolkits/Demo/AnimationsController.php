<?php

namespace Surface\Toolkits\Demo;

use ReflectionException;
use Surface\Contracts\Toolkits\Mail\Views\ButtonClicked;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Toolkits\View;

/**
 * The Animations page: five of Blend2D's demos, drawn by the engine the application draws its
 * canvases with. The route '/animations' calls index(); the page's Back button calls back().
 */
class AnimationsController
{
    /**
     * @throws ReflectionException
     */
    public function index(): void
    {
        View::render(AnimationsView::class);
    }

    public function back(ButtonClicked $clicked): void
    {
        app(Navigator::class)->back();
    }
}
