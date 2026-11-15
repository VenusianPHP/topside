<?php

namespace Surface\Rendering\Painters;

use ReflectionException;
use Surface\Contracts\Rendering\RenderingException;
use Surface\Rendering\LibraryManager;

class PaintingLibraryManager extends LibraryManager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('rendering.painter.default');
    }
}
