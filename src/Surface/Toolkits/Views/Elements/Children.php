<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Where a component's render() puts what its user nested inside it:
 *
 *     Card::make(title: 'Hi')->with(Label::make('inside'))
 *
 * puts the label where Card's render() wrote Children::slot(). Replaced before anything is
 * mounted; it is never a primitive.
 */
final class Children extends Element
{
    public static function slot(): self
    {
        return new self();
    }

    /**
     * @throws ViewException Always: only a component's render() has a slot to fill.
     */
    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        throw new ViewException("Children::slot() inside '{$parent->path()}' is in no component's render(): only a component has children to put there.");
    }

    public function kind(): string
    {
        return 'children';
    }
}
