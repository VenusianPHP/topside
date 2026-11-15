<?php

namespace Surface\Rendering\Rasterization;

use ReflectionException;
use Surface\Contracts\Rendering\RenderingException;
use Surface\Rendering\LibraryManager;

class RasterizationLibraryManager extends LibraryManager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('rendering.rasterizer.default');
    }
}