<?php

namespace Surface\Typography\Catalogs;

use Surface\Contracts\Typography\FontCatalog;

/**
 * A font catalog driver: a name and the catalog it hands out. A package that finds fonts registers
 * one on Typography's catalogs manager (app('typography.catalogs')->extend()).
 */
abstract class FontCatalogDriver
{
    /** The name it is registered under. */
    abstract public function driver(): string;

    /** Its catalog: one per driver, made on first use. */
    abstract public function catalog(): FontCatalog;
}
