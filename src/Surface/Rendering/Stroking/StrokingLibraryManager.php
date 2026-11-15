<?php

namespace Surface\Rendering\Stroking;

use ReflectionException;
use Surface\Rendering\LibraryManager;

/** The stroking drivers packages register: rendering.stroker.default names the one in use. */
class StrokingLibraryManager extends LibraryManager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('rendering.stroker.default');
    }
}
