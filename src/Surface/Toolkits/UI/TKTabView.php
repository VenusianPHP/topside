<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\UI\Concerns\IgnoresItsOwnWrites;
use Surface\Contracts\Toolkits\Mail\Views\TabSelected;
use Surface\Contracts\Toolkits\UI\TKColumn as ColumnContract;
use Surface\Contracts\Toolkits\UI\TKTabView as PrimitiveContract;

/**
 * Pages behind tabs, each a column. Which is selected is held here, so it answers the same on
 * every toolkit: the first page added, then what is selected, and when that page goes, the
 * one that took its place (else the last).
 *
 * Toolkits select on their own as tabs come, go and move, so every write is inside write().
 */
abstract class TKTabView extends TKGroup implements PrimitiveContract
{
    use IgnoresItsOwnWrites;

    /** @var array<string, string> page name => title */
    private array $titles = [];

    private ?string $selected = null;

    private bool $adding = false;

    /** The whole view is going: nothing is selected in its place. */
    private bool $emptying = false;

    /**
     * @throws ToolkitException When removed, the name is refused, or the index is out of range.
     */
    public function addPage(string $name, string $title, ?int $index = null, int $spacing = 0, int $padding = 0): ColumnContract
    {
        $this->admit($name);
        $count = count($this->children);
        if (! is_null($index) && ($index < 0 || $index > $count)) {
            throw new ToolkitException("A page goes at 0 to {$count} in '{$this->path()}', got {$index}.");
        }

        $this->titles[$name] = $title;
        $this->adding = true;
        try {
            $page = $this->create(ColumnContract::class, $name, $spacing, $padding);
        } finally {
            $this->adding = false;
            if (! isset($this->children[$name])) {
                unset($this->titles[$name]);
            }
        }

        if (! is_null($index) && $index < $count) {
            $this->arrange($page, $index);
        }

        return $page;
    }

    public function pages(): array
    {
        return array_map(strval(...), array_keys($this->children));
    }

    /**
     * @throws ToolkitException When removed, or there is no such page.
     */
    public function setTitle(string $page, string $title): static
    {
        $this->guardLive();
        $child = $this->page($page);

        $this->titles[$page] = $title;
        $this->write(fn () => $this->applyTitle($child, $title));

        return $this;
    }

    /**
     * @throws ToolkitException When there is no such page.
     */
    public function title(string $page): string
    {
        $this->page($page);

        return $this->titles[$page];
    }

    public function selected(): ?string
    {
        return $this->selected;
    }

    /**
     * Posts nothing: the app changed it, it knows.
     *
     * @throws ToolkitException When removed, or there is no such page.
     */
    public function select(string $page): static
    {
        $this->guardLive();
        $child = $this->page($page);

        $this->selected = $page;
        $this->write(fn () => $this->applySelected($child));

        return $this;
    }

    /**
     * The user chose the page at $index: record it and post TabSelected.
     */
    protected function chosen(int $index): void
    {
        $page = $this->children()[$index] ?? null;
        if (is_null($page) || $page->name() === $this->selected || ! $this->hearsUser()) {
            return;
        }

        $name = $page->name();
        $this->selected = $name;
        $this->window()->post(new TabSelected($this->window()->name(), $this->path(), $this->uuid(), $name, $index, $this->titles[$name]));
    }

    /**
     * Where a page is among the tabs.
     */
    protected function indexOf(TKPrimitive $page): int
    {
        return (int) array_search($page, $this->children(), true);
    }

    /**
     * @throws ToolkitException For anything but a page.
     */
    protected function create(string $contract, string $name, mixed ...$with): TKPrimitive
    {
        if (! $this->adding) {
            throw new ToolkitException("'{$this->path()}' holds pages: addPage('{$name}', ...) makes one, and what is shown goes inside it.");
        }

        return parent::create($contract, $name, ...$with);
    }

    protected function adopt(TKPrimitive $child): void
    {
        $this->selected ??= $child->name();
        $this->write(function () use ($child): void {
            parent::adopt($child);
            $this->applySelected($this->page($this->selected));
        });
    }

    protected function arrange(TKPrimitive $child, int $index): void
    {
        $this->write(function () use ($child, $index): void {
            parent::arrange($child, $index);
            $this->applySelected($this->page($this->selected));
        });
    }

    protected function release(TKPrimitive $child): void
    {
        $index = $this->indexOf($child);
        $this->write(function () use ($child, $index): void {
            parent::release($child);
            unset($this->titles[$child->name()]);

            $left = $this->children();
            if ($left === []) {
                $this->selected = null;

                return;
            }
            if ($this->emptying) {
                return;
            }
            if ($this->selected === $child->name()) {
                $this->selected = $left[min($index, count($left) - 1)]->name();
            }
            $this->applySelected($this->page($this->selected));
        });
    }

    protected function removeChildren(): void
    {
        $this->emptying = true;
        parent::removeChildren();
    }

    /**
     * @throws ToolkitException
     */
    private function page(string $name): TKPrimitive
    {
        return $this->children[$name] ?? throw new ToolkitException("'{$this->path()}' has no page '{$name}'.");
    }

    abstract protected function applyTitle(TKPrimitive $page, string $title): void;

    abstract protected function applySelected(TKPrimitive $page): void;
}
