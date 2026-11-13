<?php

namespace Surface\Rendering\Raycasting;

use Surface\Contracts\Rendering\RenderingException;
use Surface\Rendering\LibraryManager;

class RaycastingLibraryManager extends LibraryManager
{
    /**
     * The first driver that can run here: a registered library binding, then 'ext', then 'native'.
     *
     * @throws RenderingException When no registered driver can run.
     */
    public function createAutoDriver(): RaycastingDriver
    {
        return $this->firstAvailable();
    }

    public function getDefaultDriver(): ?string
    {
        return $this->config->get('rendering.raycaster.default', 'auto');
    }
}
