<?php

namespace Surface\Rendering\Framebuffers;

use ReflectionException;
use Surface\Contracts\Rendering\RenderingException;
use Surface\Rendering\LibraryManager;

class FramebufferLibraryManager extends LibraryManager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('rendering.framebuffer.default');
    }
}