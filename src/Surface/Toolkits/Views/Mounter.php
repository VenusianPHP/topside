<?php

namespace Surface\Toolkits\Views;

use Closure;
use Throwable;
use Surface\Toolkits\Views\Elements\Element;
use Surface\Toolkits\Views\Elements\Children;
use Surface\Toolkits\Views\Elements\ComponentElement;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Makes the primitives under a node match a list of elements, changing as little as it can.
 *
 * Each element is matched with a primitive already there: by key when it has one, otherwise
 * by its place among the children with no key. A null in the list is a hole: it holds its
 * place and builds nothing, so a conditional child ($show ? $b : null) leaves the children
 * after it matched as they were. A match of the same shape is changed with setters (only
 * what differs); anything else is built, and what is left over is removed. Then each child
 * is moved to its place, if it is not already there.
 *
 * A component used inside the view is matched the same way, by its class: a match keeps
 * the component instance (and so its state), takes the new props, and renders again.
 *
 * Everything goes through the primitives' own factories and setters, so the toolkit's
 * concrete classes, from its driver's primitive map, do the work.
 */
final class Mounter
{
    /**
     * @param (Closure(class-string<Component>): Component)|null $make builds a component; the container by default
     * @param Mount|null $mount what the view is mounted in, given to every component it builds
     */
    /** True once the view's first render is done. */
    private bool $inserting = false;

    public function __construct(private ?Closure $make = null, private readonly ?Mount $mount = null)
    {
        $this->make ??= static fn (string $class): Component => app($class);
    }

    /**
     * @param list<Element|null> $elements
     * @throws ViewException For two siblings with the same key, or children for a primitive
     *         that holds none.
     */
    public function reconcile(Mounted $parent, array $elements): void
    {
        $group = $parent->primitive;
        if (array_filter($elements) !== [] && ! $group instanceof TKPrimitiveGroup) {
            throw new ViewException("'{$group->path()}' holds no children.");
        }

        [$keyed, $unkeyed] = $this->index($parent->children);

        /** @var list<array{Element, ?Mounted, ?int}> element, its match, its slot */
        $wanted = [];
        $leftover = [];
        $slot = 0;
        $seen = [];

        foreach ($elements as $element) {
            if (is_null($element)) {
                $slot++;
                continue;
            }

            $key = $element->keyed();
            if (! is_null($key)) {
                if (isset($seen[$key])) {
                    throw new ViewException("Two children of '{$group->path()}' have the key '".self::printable($key)."'.");
                }
                $seen[$key] = true;
                $candidate = $keyed[$key] ?? null;
                unset($keyed[$key]);
                $at = null;
            } else {
                $at = $slot++;
                $candidate = $unkeyed[$at] ?? null;
                unset($unkeyed[$at]);
            }

            if (! is_null($candidate) && ! $candidate->element->sameShape($element)) {
                $leftover[] = $candidate;
                $candidate = null;
            }

            $wanted[] = [$element, $candidate, $at];
        }

        // Removed before anything is built, so their names are free again.
        foreach ([...$leftover, ...array_values($keyed), ...array_values($unkeyed)] as $gone) {
            $gone->primitive->remove();
        }

        $children = [];
        try {
            foreach ($wanted as $index => [$element, $node, $at]) {
                $node = match (true) {
                    is_null($node) => $this->build($parent, $element),
                    $element instanceof ComponentElement => $this->renderAgain($parent, $node, $element),
                    default => $this->patch($node, $element),
                };
                $node->slot = $at;

                /** @var TKPrimitiveGroup $group */
                if (array_search($node->primitive, $group->children(), true) !== $index) {
                    $node->primitive->moveTo($index);
                }

                $children[] = $node;
            }
        } finally {
            // Should a render() throw partway, the record still holds every primitive on
            // screen (those placed, then those matched but not reached), so the next render
            // or unmount finds and removes them.
            $unreached = array_filter(
                array_column(array_slice($wanted, count($children)), 1),
                static fn (?Mounted $node): bool => ! is_null($node) && ! $node->primitive->isRemoved(),
            );
            $parent->children = [...$children, ...array_values($unreached)];
        }
    }

    /**
     * The handlers of everything under $node, by primitive uuid, then by the mail they answer.
     *
     * @return array<string, array<string, Closure>>
     */
    public function handlers(Mounted $node): array
    {
        $handlers = [];

        foreach ($node->children as $child) {
            if ($found = $child->described()->handlersFor($child->primitive)) {
                $handlers[$child->primitive->uuid()] = $found;
            }
            $handlers += $this->handlers($child);
        }

        return $handlers;
    }

    private function patch(Mounted $node, Element $element): Mounted
    {
        $before = $node->element;
        $element->apply($node->primitive, $before);
        $node->element = $element;
        $this->reconcile($node, $element->children());
        $element->settle($node->primitive, $before, $node->children);

        return $node;
    }

    private function build(Mounted $parent, Element $element): Mounted
    {
        $node = $this->buildNode($parent, $element);

        // Inserted into a view already on screen: it comes in as it would be shown. The first
        // render of a view plays nothing.
        if ($this->inserting) {
            $node->primitive->playAppear();
        }

        return $node;
    }

    /**
     * A view's first render is done: what is built from now on is inserted, and plays its
     * appear transition.
     *
     * @internal
     */
    public function settled(): void
    {
        $this->inserting = true;
    }

    private function buildNode(Mounted $parent, Element $element): Mounted
    {
        if (! $element instanceof ComponentElement) {
            $node = new Mounted($this->buildPrimitive($parent, $element, $element), $element);

            return $this->fillOrRemove($node, $element);
        }

        $component = ($this->make)($element->class)->mountedIn($this->mount)->assign($element->assigned());
        $root = $this->describe($component, $element);

        $node = new Mounted($this->buildPrimitive($parent, $element, $root), $element);
        $node->component = $component;
        $node->rendered = $root;

        return $this->fillOrRemove($node, $root);
    }

    /**
     * Build what goes inside a primitive just built; should that fail, the primitive goes too,
     * so nothing is left on screen that no record holds.
     *
     * @param Element $described what the primitive was made from
     */
    private function fillOrRemove(Mounted $node, Element $described): Mounted
    {
        try {
            $this->reconcile($node, $described->children());
            $described->settle($node->primitive, null, $node->children);
        } catch (Throwable $e) {
            $node->primitive->remove();
            throw $e;
        }

        return $node;
    }

    /**
     * A component still on screen: the parent's props again, then its render(). A root of
     * the same shape is changed in place; another root replaces the primitive, keeping the
     * component and its state.
     */
    private function renderAgain(Mounted $parent, Mounted $node, ComponentElement $element): Mounted
    {
        $node->component->assign($element->assigned());
        $root = $this->describe($node->component, $element);

        $before = $node->rendered;
        if ($root->sameShape($before)) {
            $root->apply($node->primitive, $before);
        } else {
            $before = null;
            $node->primitive->remove();
            $node->children = [];
            $node->serial = 0;
            $node->primitive = $this->buildPrimitive($parent, $element, $root);
        }

        $node->element = $element;
        $node->rendered = $root;
        $this->reconcile($node, $root->children());
        $root->settle($node->primitive, $before, $node->children);

        return $node;
    }

    /**
     * Build $described's primitive in $parent, named after $element: its name(), or its kind
     * and a number.
     */
    private function buildPrimitive(Mounted $parent, Element $element, Element $described): TKPrimitive
    {
        /** @var TKPrimitiveGroup $group */
        $group = $parent->primitive;
        $name = $element->named() ?? $element->kind().(++$parent->serial);

        $primitive = $described->build($group, $name);
        $described->apply($primitive, null);

        return $primitive;
    }

    /**
     * What $component's render() describes, with the settings made where it is used laid
     * over its root, and what it was given in place of Children::slot().
     *
     * @throws ViewException When the root itself is Children::slot().
     */
    private function describe(Component $component, ComponentElement $element): Element
    {
        $root = $component->render();
        if ($root instanceof Children) {
            throw new ViewException($component::class.'::render() returns Children::slot(): wrap the slot in a column or row.');
        }

        $root->inherit($element);
        $root->fillSlot($element->slot());

        return $root;
    }

    /**
     * @param list<Mounted> $children
     * @return array{array<string|int, Mounted>, array<int, Mounted>} by key, and those with none by slot
     */
    private function index(array $children): array
    {
        $keyed = [];
        $unkeyed = [];

        foreach ($children as $child) {
            $key = $child->element->keyed();
            is_null($key) ? $unkeyed[$child->slot] = $child : $keyed[$key] = $child;
        }

        return [$keyed, $unkeyed];
    }

    private static function printable(string|int $key): string
    {
        // A key written by sharp or justify() carries where it came from before its last \0.
        $key = (string) $key;

        return str_contains($key, "\0") ? substr(strrchr($key, "\0"), 1) : $key;
    }
}
