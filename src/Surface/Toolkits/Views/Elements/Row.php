<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Children side by side, left to right.
 */
class Row extends Stack
{
    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->row($name, $this->spacing, $this->padding);
    }

    public function kind(): string
    {
        return 'row';
    }
}
