<?php

namespace Surface\Typography\Catalogs;

use Voyager\NutsAndBolts\Manager;

/** The font catalog drivers packages register: typography.catalog.default names the one in use. */
class FontCatalogLibraryManager extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('typography.catalog.default');
    }
}
