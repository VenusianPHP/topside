<?php

namespace Surface\Toolkits\Menus;

use Closure;
use Surface\Contracts\Toolkits\Menus\MenuNode;

/**
 * What every menu definition shares: it is a readonly value, and every change is a copy.
 */
abstract readonly class Node implements MenuNode
{
    use CopiesItself;

    /**
     * Whether a platform shows it. All do, but for the standard items only macOS has.
     */
    public function shownOn(bool $mac): bool
    {
        return true;
    }

    /**
     * The callback it runs, if it has one. A definition in a config file has none.
     */
    public function callback(): ?Closure
    {
        return null;
    }

    /**
     * The id it posts mail under, if it has one. The mail's name holds the path to it: the ids
     * of the menus above it, then this one.
     */
    public function mailId(): ?string
    {
        return null;
    }

    /**
     * How messages name it: "item 'Save'".
     */
    abstract public function describe(): string;

    /**
     * @throws MenuException When $id is not lower case letters, digits and '-'.
     */
    protected static function checkId(?string $id, string $what): void
    {
        if (! is_null($id) && preg_match('/^[a-z0-9-]+\z/', $id) !== 1) {
            throw new MenuException(ucfirst($what).": the id '{$id}' must be lower case letters, digits and '-', like 'save-as'.");
        }
    }
}
