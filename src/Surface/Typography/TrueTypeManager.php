<?php

namespace Surface\Typography;

use Surface\Contracts\Typography\FontDriver;
use Surface\Contracts\Typography\FontException;
use Surface\Typography\Extended\ExtendedFontDriver;
use Voyager\NutsAndBolts\Manager;

/**
 * Where TrueType text is shaped and rasterised. 'extended' (the only driver, and the default) needs ext-freetype,
 * ext-harfbuzz and ext-fontconfig; there is no native PHP driver.
 *
 * @method FontDriver driver(?string $driver = null)
 */
class TrueTypeManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('fonts.driver', 'extended');
    }

    /** @throws FontException When the font exts are not loaded in this PHP. */
    public function createExtendedDriver(): FontDriver
    {
        return new ExtendedFontDriver();
    }
}
