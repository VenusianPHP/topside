<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * A component used inside another view: what Card::make(title: 'Hi') returns. It is not a
 * primitive of its own: the Mounter builds the component once, keeps it while it stays on
 * screen, and mounts what its render() describes in this element's place.
 *
 *  - The named arguments of make() are set on the component's public properties on every
 *    render of the view that uses it, so what a parent passes is the parent's to change.
 *    Everything else on the component is its own state and lasts as long as it is shown.
 *  - What ->with() is given goes where the component's render() puts Children::slot().
 *  - Settings made here (fill(), align(), hidden(), ...) are laid over its root element.
 */
final class ComponentElement extends Element
{
    /** @var list<Element|null> */
    private array $slot = [];

    /**
     * @param class-string<Component> $class
     * @param array<string, mixed> $assign
     */
    public function __construct(
        public readonly string $class,
        private readonly array $assign = [],
    ) {}

    /**
     * What goes into the component's Children::slot(), in order.
     */
    public function with(Element|null|false ...$children): static
    {
        foreach ($children as $child) {
            $this->slot[] = $child instanceof Element ? $child : null;
        }

        return $this;
    }

    /**
     * @return array<string, mixed> the props, by property name
     */
    public function assigned(): array
    {
        return $this->assign;
    }

    /**
     * @return list<Element|null>
     */
    public function slot(): array
    {
        return $this->slot;
    }

    /**
     * A Children::slot() written in this element's content belongs to the component whose
     * render() wrote it, so it is filled here too.
     */
    public function fillSlot(array $slot): void
    {
        $this->slot = self::splice($this->slot, $slot);
    }

    /**
     * Never built as a primitive: the Mounter builds the component's root instead.
     *
     * @throws ViewException Always.
     */
    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        throw new ViewException("{$this->class} is a component: the Mounter mounts what its render() describes.");
    }

    /**
     * The class's short name, lowercase first: Card gives card1, card2, ...
     */
    public function kind(): string
    {
        return lcfirst(substr(strrchr('\\'.$this->class, '\\'), 1));
    }

    protected function shape(): array
    {
        return ['class' => $this->class];
    }
}
