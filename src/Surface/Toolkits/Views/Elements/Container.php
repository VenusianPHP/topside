<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * An element that holds others, in order.
 */
abstract class Container extends Element
{
    /** @var list<Element|null> null holds the place of a child left out */
    protected array $children = [];

    /**
     * The children, in order. Null and false are left out, so a child can be conditional:
     * ->with($a, $show ? $b : null). A child left out still holds its place, so the children
     * after it stay the same primitives whether it is there or not.
     */
    public function with(Element|null|false ...$children): static
    {
        foreach ($children as $child) {
            $this->children[] = $child instanceof Element ? $child : null;
        }

        return $this;
    }

    public function fillSlot(array $slot): void
    {
        $this->children = self::splice($this->children, $slot);
    }

    public function children(): array
    {
        return $this->children;
    }

    /**
     * Run $handler when the user clicks the container: a primary press and its release on it
     * or on passive content inside it (a label, an image). A control inside keeps its own
     * click. It is given the GroupClicked mail; a method that takes nothing ignores it.
     */
    public function onClick(Closure $handler): static
    {
        $this->set('clickable', true);

        return $this->on('click', $handler);
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'clickable' => fn (TKPrimitive $p, bool $v) => $p instanceof TKPrimitiveGroup && $p->setClickable($v),
        ];
    }

    protected function defaults(): array
    {
        return parent::defaults() + ['clickable' => false];
    }
}
