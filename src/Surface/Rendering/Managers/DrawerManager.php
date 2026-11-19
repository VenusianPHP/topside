<?php

namespace Surface\Rendering\Managers;

use Surface\Contracts\Rendering\DrawerException;
use Voyager\NutsAndBolts\Manager;

class DrawerManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $name = $this->config->get('rendering.drawers.default');

        return is_string($name) && $name !== '' ? $name : throw DrawerException::noneConfigured();
    }
}
