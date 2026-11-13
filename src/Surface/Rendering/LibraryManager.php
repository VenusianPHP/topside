<?php

namespace Surface\Rendering;

use Surface\Contracts\Rendering\RenderingException;
use Voyager\NutsAndBolts\Manager;

/**
 * A manager of rendering drivers that other packages register by name with extend().
 *
 * 'auto' takes the first driver that can run in this PHP, in this order:
 *   1. every driver a package registered under its own name (a C library binding), in the
 *      order they were registered;
 *   2. 'ext', VelvetGE's own C extension;
 *   3. 'native', VelvetGE in PHP, which needs nothing.
 *
 * A driver that cannot run here says so by throwing a RenderingException from its creator or
 * constructor (its extension is not loaded, or is too old); 'auto' then tries the next one.
 * Any other throwable is a defect and is not caught.
 */
abstract class LibraryManager extends Manager
{
    /** VelvetGE's own drivers: what 'auto' tries last, in this order. */
    protected const array LAST_RESORTS = ['ext', 'native'];

    /**
     * The first registered driver that can run in this PHP.
     *
     * @throws RenderingException When no registered driver can run, with each one's reason.
     */
    protected function firstAvailable(): object
    {
        $registered = array_keys($this->customCreators);
        $libraries = array_values(array_diff($registered, ['auto', ...self::LAST_RESORTS]));
        $last = array_values(array_intersect(self::LAST_RESORTS, $registered));

        $refused = [];
        foreach ([...$libraries, ...$last] as $name) {
            try {
                return $this->driver($name);
            } catch (RenderingException $e) {
                $refused[] = "'{$name}' ({$e->getMessage()})";
            }
        }

        throw new RenderingException($refused === []
            ? static::class.' has no driver registered, so \'auto\' has nothing to choose from.'
            : static::class.' found no driver that can run here: '.implode('; ', $refused).'.');
    }
}
