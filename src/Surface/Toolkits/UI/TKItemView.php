<?php

namespace Surface\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\UI\TKItemView as PrimitiveContract;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\UI\Concerns\IgnoresItsOwnWrites;
use Surface\Contracts\Toolkits\Mail\Views\ItemActivated;
use Surface\Contracts\Toolkits\Mail\Views\ItemActionClicked;
use Surface\Contracts\Toolkits\Mail\Views\ViewScrolled;

/**
 * Topside keeps every item; the native asks for the ones in view. The toolkit calls
 * activated(), actionClicked() and scrolled() from the native's own signals; the mail is made
 * here, for enabled items only.
 */
abstract class TKItemView extends TKPrimitive implements PrimitiveContract
{
    use IgnoresItsOwnWrites;

    /**
     * @var list<object>
     */
    protected array $items;

    protected bool $watching_scroll = false;

    /**
     * @param list<object> $items
     * @throws ToolkitException When the name is not valid or an item is not of the view's item class.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        array $items = [],
    ) {
        $this->items = $this->guardItems($items);
        parent::__construct($name, $window, $parent, $placement);
    }

    /**
     * @return class-string
     */
    abstract protected static function itemClass(): string;

    public function count(): int
    {
        return count($this->items);
    }

    public function items(): array
    {
        return $this->items;
    }

    public function item(int $index): object
    {
        return $this->items[$this->guardIndex($index)];
    }

    public function setItems(array $items): static
    {
        $this->guardLive();
        $this->items = $this->guardItems($items);
        $this->write(fn () => $this->applyItems($this->items));

        return $this;
    }

    public function insertItems(int $at, array $items): static
    {
        $this->guardLive();
        if ($at < 0 || $at > count($this->items)) {
            throw new ToolkitException("Insert index {$at} is out of range (0..".count($this->items).').');
        }
        $items = $this->guardItems($items);
        if ($items === []) {
            return $this;
        }
        array_splice($this->items, $at, 0, $items);
        $this->write(fn () => $this->applyInserted($at, $items));

        return $this;
    }

    public function removeItems(int $at, int $count = 1): static
    {
        $this->guardLive();
        if ($count < 1 || $at < 0 || $at + $count > count($this->items)) {
            throw new ToolkitException("Items {$at}..".($at + $count - 1).' are out of range ('.count($this->items).' items).');
        }
        array_splice($this->items, $at, $count);
        $this->write(fn () => $this->applyRemoved($at, $count));

        return $this;
    }

    public function updateItem(int $index, object $item): static
    {
        $this->guardLive();
        $this->guardIndex($index);
        $this->items[$index] = $this->guardItems([$item])[0];
        $this->write(fn () => $this->applyUpdated($index, $item));

        return $this;
    }

    public function scrollPosition(): ScrollPosition
    {
        $this->guardLive();

        return $this->nativeScrollPosition();
    }

    public function scrollTo(float $x, float $y): static
    {
        $this->guardLive();
        if (! is_finite($x) || ! is_finite($y)) {
            throw new ToolkitException("Item view '{$this->path()}' scroll position must be finite.");
        }
        $this->write(fn () => $this->applyScrollTo(max(0.0, min(1.0, $x)), max(0.0, min(1.0, $y))));

        return $this;
    }

    public function watchScroll(bool $on = true): static
    {
        $this->guardLive();
        $this->watching_scroll = $on;

        return $this;
    }

    public function isWatchingScroll(): bool
    {
        return $this->watching_scroll;
    }

    /**
     * Whether the native may post for the item: it exists and is not disabled.
     */
    protected function itemEnabled(int $index): bool
    {
        $item = $this->items[$index] ?? null;

        return $item !== null && (! property_exists($item, 'enabled') || $item->enabled);
    }

    /**
     * The user opened the item: a double click, or Return.
     */
    protected function activated(int $index): void
    {
        if ($this->hearsUser() && $this->itemEnabled($index)) {
            $this->window()->post(new ItemActivated($this->window()->name(), $this->path(), $this->uuid(), $index));
        }
    }

    /**
     * The user clicked the item's action.
     */
    protected function actionClicked(int $index): void
    {
        if ($this->hearsUser() && $this->itemEnabled($index)) {
            $this->window()->post(new ItemActionClicked($this->window()->name(), $this->path(), $this->uuid(), $index));
        }
    }

    /**
     * The native scrolled: ViewScrolled, the latest per pump, while watching.
     */
    protected function scrolled(): void
    {
        if ($this->watching_scroll && ! $this->isWriting() && ! $this->isRemoved()) {
            $this->window()->postLatest('view.scrolled.'.$this->uuid(), new ViewScrolled($this->window()->name(), $this->path(), $this->uuid(), $this->nativeScrollPosition()));
        }
    }

    /**
     * @param array<mixed> $items
     * @return list<object>
     * @throws ToolkitException
     */
    protected function guardItems(array $items): array
    {
        $class = static::itemClass();
        if (! array_is_list($items) || array_filter($items, fn (mixed $item): bool => ! $item instanceof $class) !== []) {
            throw new ToolkitException('Items must be a list of '.$class.'.');
        }

        return $items;
    }

    /**
     * @throws ToolkitException When outside the items.
     */
    protected function guardIndex(int $index): int
    {
        if ($index < 0 || $index >= count($this->items)) {
            throw new ToolkitException("Item {$index} is out of range (".count($this->items).' items).');
        }

        return $index;
    }

    /**
     * Replace every native item.
     * @param list<object> $items
     */
    abstract protected function applyItems(array $items): void;

    /**
     * @param list<object> $items
     */
    abstract protected function applyInserted(int $at, array $items): void;

    abstract protected function applyRemoved(int $at, int $count): void;

    abstract protected function applyUpdated(int $index, object $item): void;

    abstract protected function applyScrollTo(float $x, float $y): void;

    abstract protected function nativeScrollPosition(): ScrollPosition;
}
