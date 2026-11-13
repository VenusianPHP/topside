<?php

namespace Surface\Toolkits\Managers;

use ReflectionException;
use Surface\Contracts\Toolkits\Composition;
use Surface\Toolkits\Drivers\ContentTreeDriver;
use Surface\Toolkits\Drivers\ToolkitTreeDriver;
use Voyager\NutsAndBolts\Manager;

class ContentTreeManager extends Manager
{
    public function render(string $composition, array $args = []): void
    {
        /** @var ContentTreeDriver $driver */
        $driver = $this->driver();

        /** @var Composition $component */
        $component = new $composition();

        $driver->render($component, $args);
    }

    public function createToolkitDriver(): ToolkitTreeDriver
    {
        return new ToolkitTreeDriver($this->vessel);
    }

    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return config('main.presentation.mode');
    }
}