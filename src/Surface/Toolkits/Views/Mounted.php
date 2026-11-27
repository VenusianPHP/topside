<?php

namespace Surface\Toolkits\Views;

use Surface\Toolkits\Views\Elements\Element;
use Surface\Contracts\Toolkits\UI\TKPrimitive;

/**
 * One primitive on screen and the element it was last made to match. The host a view is
 * mounted in has no element. For a component used inside a view, $element is where it is
 * used, $component the instance kept across renders, $inner the components it renders as its
 * root (and they as theirs), kept the same way, and $rendered the root the primitive was made
 * from: the first that is no component.
 *
 * @internal The Mounter's record.
 */
final class Mounted
{
    /** @var list<Mounted> in display order */
    public array $children = [];

    /** The last number given to a generated child name: label1, button2, ... */
    public int $serial = 0;

    /**
     * For a child with no key, its place among its parent's children with no key, holes
     * included: what it is matched by on the next render.
     */
    public ?int $slot = null;

    public ?Component $component = null;

    /** @var list<Component> what $component renders as its root, outermost first */
    public array $inner = [];

    public ?Element $rendered = null;

    public function __construct(
        public TKPrimitive $primitive,
        public ?Element $element = null,
    ) {}

    /**
     * The element the primitive was made from.
     */
    public function described(): ?Element
    {
        return $this->rendered ?? $this->element;
    }
}
