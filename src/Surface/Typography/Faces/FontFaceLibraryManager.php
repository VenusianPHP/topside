<?php

namespace Surface\Typography\Faces;

use Voyager\NutsAndBolts\Manager;

/** The font face drivers packages register: typography.face.default names the one in use. */
class FontFaceLibraryManager extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('typography.face.default');
    }
}
