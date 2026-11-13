<?php

namespace Surface\Contracts\Toolkits;

use Surface\Contracts\Core\TopsideException;

class ToolkitRuntimeException extends TopsideException
{
    public static function missingVariable(string $variable): static
    {
        return new static('Variable '.$variable.' not found');
    }

    public static function instanceOpen(string $variable): static
    {
        return new static("A window named '{$variable}' is already open.");
    }
}