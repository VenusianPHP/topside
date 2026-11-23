<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TKTabView;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\TabSelected;

/**
 * Pages behind tabs, each a <Tab>. It needs no state: bind selected only to choose the tab
 * from code. It takes the room there is unless told fill="false".
 *
 *     <TabView :selected="$tab" @change="fn ($i) => $this->tab = $i">
 *         <Tab title="Files">...</Tab>
 *     </TabView>
 */
class TabView extends Container
{
    /** @var list<string> the pages there were when this one settled */
    private array $settled = [];

    final public function __construct(
        protected readonly ?int $selected = null,
    ) {
        $this->fill();
    }

    /**
     * @param int|null $selected the tab's place, from 0; null leaves it to the user
     */
    public static function make(?int $selected = null): static
    {
        return new static($selected);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->tabs($name);
    }

    public function kind(): string
    {
        return 'tabView';
    }

    /**
     * A hidden tab is left out: not every toolkit can hide one.
     *
     * @throws ViewException For a child that is not a tab.
     */
    public function children(): array
    {
        return array_map(static function (?Element $child): ?Element {
            if (! is_null($child) && ! $child instanceof Tab && ! $child instanceof ComponentElement) {
                throw new ViewException("A TabView holds Tabs: put the {$child->kind()} inside a Tab.");
            }

            return $child?->valueOf('visible') === false ? null : $child;
        }, $this->children);
    }

    /**
     * Run $handler when the user chooses a tab, with its place, its title and the mail.
     *
     * @param Closure(int, string, TabSelected): mixed $handler
     */
    public function onChange(Closure $handler): static
    {
        return $this->on('change', static fn (TabSelected $mail) => $handler($mail->index, $mail->title, $mail));
    }

    /**
     * Selected once the pages are there. Set again only when the value or the pages changed,
     * so that the user's own choice stays.
     */
    public function settle(TKPrimitive $primitive, ?Element $before, array $children): void
    {
        /** @var TKTabView $primitive */
        $pages = $primitive->pages();
        if (is_null($this->selected) || ! isset($pages[$this->selected])) {
            return;
        }

        $changed = ! $before instanceof self
            || $before->selected !== $this->selected
            || $before->settled !== $pages;
        $this->settled = $pages;
        if ($changed && $primitive->selected() !== $pages[$this->selected]) {
            $primitive->select($pages[$this->selected]);
        }
    }
}
