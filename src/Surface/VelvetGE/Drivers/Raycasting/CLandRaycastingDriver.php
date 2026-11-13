<?php

namespace Surface\VelvetGE\Drivers\Raycasting;

use RaycastGrid;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Contracts\Rendering\Raycasting\Marcher;
use Surface\VelvetGE\Raycasting\CLandMarcher;

/** The casting in C, through ext-raycast. */
class CLandRaycastingDriver extends MarcherRaycastingDriver
{
    /** The oldest ext-raycast this driver runs on. */
    public const string MINIMUM = '0.11.0';

    /** Whether this PHP has an ext-raycast of at least MINIMUM loaded. */
    public static function available(): bool
    {
        return class_exists(RaycastGrid::class, false) && version_compare((string) phpversion('raycast'), self::MINIMUM, '>=')
            && method_exists(RaycastGrid::class, 'viewInto');    // a 0.11.0 built before viewInto() is too old, with the same version
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

    protected function marcher(Grid $grid): Marcher
    {
        return new CLandMarcher($grid);
    }
}
