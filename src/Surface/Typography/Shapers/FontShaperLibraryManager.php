<?php

namespace Surface\Typography\Shapers;

use Voyager\NutsAndBolts\Manager;

/** The font shaper drivers packages register: typography.shaper.default names the one in use. */
class FontShaperLibraryManager extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('typography.shaper.default');
    }
}
