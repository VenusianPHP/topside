<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Children one under another.
 */
class Column extends Stack
{
    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->column($name, $this->spacing, $this->padding);
    }

    public function kind(): string
    {
        return 'column';
    }
}
