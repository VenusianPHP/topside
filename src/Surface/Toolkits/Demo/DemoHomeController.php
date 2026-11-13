<?php

namespace Surface\Toolkits\Demo;

use ReflectionException;
use Surface\Toolkits\View;

class DemoHomeController
{
    /**
     * @throws ReflectionException
     */
    public function index(): void
    {
        View::render(DemoHomeView::class, []);
    }
}