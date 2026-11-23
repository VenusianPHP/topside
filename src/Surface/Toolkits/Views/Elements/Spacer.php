<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\UI\TKRow as RowContract;

/**
 * Empty space that takes what its column or row has spare, pushing what follows it to the far
 * end. Spare room is shared equally among what fills, so two spacers centre what is between.
 *
 * An empty column that fills along its parent's axis only: one that also filled across would
 * make AppKit and GTK grow the parent.
 */
class Spacer extends Element
{
    final public function __construct() {}

    public static function make(): static
    {
        return new static();
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        $spacer = $parent->column($name);

        $parent instanceof RowContract ? $spacer->fill(true, false) : $spacer->fill(false, true);

        return $spacer;
    }

    public function kind(): string
    {
        return 'spacer';
    }

    /**
     * The fill is the spacer itself, set in build(); a view cannot set it.
     */
    public function fill(bool $horizontal = true, bool $vertical = true): static
    {
        return $this;
    }
}
