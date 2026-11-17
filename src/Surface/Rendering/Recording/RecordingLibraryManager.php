<?php

namespace Surface\Rendering\Recording;

use ReflectionException;
use Surface\Contracts\Rendering\RenderingException;
use Surface\Rendering\LibraryManager;

/**
 * Rendering's recorders, by name: 'php' (in PHP) and 'c' (ext-pixel-queue), registered by
 * RenderingServiceProvider, and any a package registers.
 */
class RecordingLibraryManager extends LibraryManager
{
    /**
     * The first driver that can run here: a registered library binding, then 'c', then 'php'.
     *
     * @throws RenderingException When no registered driver can run.
     */
    public function createAutoDriver(): RecordingDriver
    {
        return $this->firstAvailable();
    }

    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('rendering.recorder.default', 'auto');
    }
}
