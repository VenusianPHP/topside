<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * A line between things: across, or down when vertical.
 *
 *     <Separator />
 *     <Separator vertical />
 */
class Separator extends Element
{
    final public function __construct(
        protected readonly bool $vertical = false,
    ) {}

    /**
     * @param bool $vertical a line down, as between the children of a row
     */
    public static function make(bool $vertical = false): static
    {
        return new static($vertical);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->separator($name, ! $this->vertical);
    }

    public function kind(): string
    {
        return 'separator';
    }

    protected function shape(): array
    {
        return ['vertical' => $this->vertical];
    }
}
