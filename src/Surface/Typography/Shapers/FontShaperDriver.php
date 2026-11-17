<?php

namespace Surface\Typography\Shapers;

use Surface\Contracts\Typography\FontShaper;

/**
 * A font shaper driver: a name and the shaper it hands out. A package that shapes text registers
 * one on Typography's shapers manager (app('typography.shapers')->extend()).
 */
abstract class FontShaperDriver
{
    /** The name it is registered under. */
    abstract public function driver(): string;

    /** Its shaper: one per driver, made on first use. */
    abstract public function shaper(): FontShaper;
}
