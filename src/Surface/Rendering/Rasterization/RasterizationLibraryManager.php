<?php

namespace Surface\Rendering\Rasterization;

use ReflectionException;
use Surface\Contracts\Rendering\RenderingException;
use Surface\Rendering\LibraryManager;

class RasterizationLibraryManager extends LibraryManager
{
    /**
     * The first driver that can run here: a registered library binding, then 'ext', then 'native'.
     *
     * @throws RenderingException When no registered driver can run.
     */
    public function createAutoDriver(): RasterizationDriver
    {
        return $this->firstAvailable();
    }

    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('rendering.rasterizer.default', 'auto');
    }
}