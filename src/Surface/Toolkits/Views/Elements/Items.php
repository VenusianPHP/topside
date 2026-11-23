<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TKItemView;
use Surface\Contracts\Toolkits\Mail\Views\ItemActivated;
use Surface\Contracts\Toolkits\Mail\Views\ItemActionClicked;

/**
 * What a ListView and a CollectionView share: items written as strings or arrays, changed
 * one by one where they differ, and handlers given the item as it was written.
 */
abstract class Items extends Element
{
    /** @var list<mixed> as written */
    protected readonly array $given;

    /** @var list<object> as the primitive takes them */
    protected readonly array $items;

    /**
     * @param array<mixed> $items
     */
    final public function __construct(array $items = [])
    {
        $this->given = array_values($items);
        $this->items = array_map(fn (mixed $item): object => $this->item($item), $this->given);
        $this->fill();
    }

    /**
     * @param array<mixed> $items
     */
    public static function make(array $items = []): static
    {
        return new static($items);
    }

    /**
     * Run $handler when the user opens an item (a double click, or Return), with the item as
     * it was written, its place and the mail.
     *
     * @param Closure(mixed, int, ItemActivated): mixed $handler
     */
    public function onActivate(Closure $handler): static
    {
        $given = $this->given;

        return $this->on('activate', static fn (ItemActivated $mail) => $handler($given[$mail->index] ?? null, $mail->index, $mail));
    }

    /**
     * Run $handler when the user clicks an item's action, as onActivate() does.
     *
     * @param Closure(mixed, int, ItemActionClicked): mixed $handler
     */
    public function onAction(Closure $handler): static
    {
        $given = $this->given;

        return $this->on('action', static fn (ItemActionClicked $mail) => $handler($given[$mail->index] ?? null, $mail->index, $mail));
    }

    /**
     * One item as written, as the primitive takes it.
     */
    abstract protected function item(mixed $item): object;

    /**
     * @param list<string> $keys
     * @return array<string, mixed> the item's fields
     * @throws ViewException For what is neither text nor those fields.
     */
    protected function fields(mixed $item, string $text, array $keys): array
    {
        if (is_scalar($item)) {
            return [$text => (string) $item];
        }
        if (! is_array($item) || ! isset($item[$text])) {
            throw new ViewException("An item of a {$this->kind()} is text, or an array with '{$text}' (and any of ".implode(', ', $keys).'), got '.get_debug_type($item).'.');
        }

        return $item;
    }

    protected function made(): array
    {
        return ['items' => $this->items];
    }

    protected function valueOf(string $prop): mixed
    {
        return $prop === 'items' ? $this->items : parent::valueOf($prop);
    }

    protected function setters(): array
    {
        return parent::setters() + ['items' => self::changeItems(...)];
    }

    /**
     * Only what differs, so that a list stays where it was scrolled to.
     *
     * @param list<object> $items
     */
    private static function changeItems(TKItemView $view, array $items): void
    {
        $held = $view->items();
        if (count($held) === count($items)) {
            foreach ($items as $index => $item) {
                if ($held[$index] != $item) {
                    $view->updateItem($index, $item);
                }
            }

            return;
        }

        $same = min(count($held), count($items));
        $start = 0;
        while ($start < $same && $held[$start] == $items[$start]) {
            $start++;
        }
        $end = 0;
        while ($end < $same - $start && $held[count($held) - 1 - $end] == $items[count($items) - 1 - $end]) {
            $end++;
        }

        $out = count($held) - $start - $end;
        if ($out > 0) {
            $view->removeItems($start, $out);
        }
        $in = array_slice($items, $start, count($items) - $start - $end);
        if ($in !== []) {
            $view->insertItems($start, $in);
        }
    }
}
