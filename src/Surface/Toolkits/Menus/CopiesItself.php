<?php

namespace Surface\Toolkits\Menus;

/**
 * For the menu definitions: readonly values whose every change is a copy, and which come back
 * from a cached config file. Both need the constructor's parameters to carry the properties'
 * names.
 */
trait CopiesItself
{
    /**
     * This value with $changes, by property name. The constructor checks the copy as it
     * checked the original.
     *
     * @param array<string, mixed> $changes
     */
    protected function with(array $changes): static
    {
        return new static(...[...get_object_vars($this), ...$changes]);
    }

    /**
     * What var_export() writes for a definition in a cached config file.
     *
     * @param array<string, mixed> $state
     */
    public static function __set_state(array $state): static
    {
        return new static(...$state);
    }
}
