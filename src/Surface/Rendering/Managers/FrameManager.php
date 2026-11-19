<?php

namespace Surface\Rendering\Managers;

use Surface\Contracts\Rendering\FrameProviderException;
use Voyager\NutsAndBolts\Manager;

class FrameManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $name = $this->config->get('rendering.frames.default');

        return is_string($name) && $name !== '' ? $name : throw FrameProviderException::noneConfigured();
    }
}