<?php

namespace Surface\VelvetGE\Drivers\Raycasting;

use RaycastGrid;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Contracts\Rendering\Raycasting\Raycaster;
use Surface\Rendering\Raycasting\RaycastingDriver;
use Surface\VelvetGE\Raycasting\CLandRaycaster;

/** The casting in C, through ext-raycast. */
class CLandRaycastingDriver extends RaycastingDriver
{
    /** The oldest ext-raycast this driver runs on. */
    public const string MINIMUM = '0.11.0';

    /** Whether this PHP has an ext-raycast of at least MINIMUM loaded. */
    public static function available(): bool
    {
        return class_exists(RaycastGrid::class, false) && version_compare((string) phpversion('raycast'), self::MINIMUM, '>=');
    }

    /** @throws RaycastException When this PHP has no ext-raycast, or one older than MINIMUM. */
    public function __construct()
    {
        if (! self::available()) {
            throw RaycastException::extensionMissing(self::MINIMUM);
        }
    }

    public function driver(): string
    {
        return 'ext';
    }

    /** Typed as the contract's: a narrower return would load the ext raycaster's class with the driver. */
    public function caster(Grid $grid): Raycaster
    {
        return new CLandRaycaster($grid);
    }
}
