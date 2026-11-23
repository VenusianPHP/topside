<?php

namespace Surface\Toolkits\Demo\Controllers;

use ReflectionException;
use Surface\Toolkits\View;
use Surface\Toolkits\Demo\Views\DemoHomeView;

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