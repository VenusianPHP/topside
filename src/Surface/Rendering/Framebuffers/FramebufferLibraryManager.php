<?php

namespace Surface\Rendering\Framebuffers;

use ReflectionException;
use Surface\Contracts\Rendering\RenderingException;
use Surface\Rendering\LibraryManager;

class FramebufferLibraryManager extends LibraryManager
{
    /**
     * The first driver that can run here: a registered library binding, then 'ext', then 'native'.
     *
     * @throws RenderingException When no registered driver can run.
     */
    public function createAutoDriver(): FramebufferDriver
    {
        return $this->firstAvailable();
    }

    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('rendering.framebuffer.default', 'auto');
    }
}