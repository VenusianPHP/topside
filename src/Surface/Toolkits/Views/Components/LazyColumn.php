<?php

namespace Surface\Toolkits\Views\Components;

use Closure;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Element;
use Surface\Toolkits\Views\Elements\Children;
use Surface\Toolkits\Views\Elements\ScrollView;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;

/**
 * A long list whose rows are built only near the view: a scroll view holding a spacer above,
 * the rows first to last (the page's children), and a spacer below, sized so the scroll bar
 * is as it would be with every row there. Every row is rowHeight high.
 *
 * The page keeps which rows it shows (first, last) and renders them, keyed. The list works out
 * from its own scrolling which rows are in view, with overscan rows beyond each edge, and when
 * that differs from first to last it asks for them through onWindow($first, $last). onNearEnd()
 * runs while the view is past nearEnd of the content.
 *
 *     <LazyColumn :count="$rows" rowHeight="296" spacing="28" :first="$first" :last="$last"
 *                 @window="shown" @nearEnd="more">
 *         @foreach (range($first, $last) as $r) <Row :key="'row-'.$r">...</Row> @endforeach
 *     </LazyColumn>
 *
 * Built from the scroll view every toolkit has.
 */
class LazyColumn extends Component
{
    /** How many rows there are. */
    public int $count = 0;

    /** The height of every row, in points. */
    public int $rowHeight = 0;

    /** The space between rows. */
    public int $spacing = 0;

    /** Round the rows, inside the scroll view: one to four values, as CSS ("20 0 24"). */
    public string $padding = '0';

    /** The rows the page shows: its children are these, in order. */
    public int $first = 0;

    public int $last = -1;

    /** Rows kept beyond each edge of the view. */
    public int $overscan = 2;

    /** How far down the content (0..1) the view's bottom edge is when onNearEnd runs. */
    public float $nearEnd = 0.85;

    /** Run with the first and last row to show, when the rows in view are others. */
    public ?Closure $onWindow = null;

    /** Run while the view is past nearEnd. */
    public ?Closure $onNearEnd = null;

    /**
     * @throws ViewException When the row height is not positive.
     */
    public function render(): Element
    {
        if ($this->rowHeight <= 0) {
            throw new ViewException("A LazyColumn's rowHeight is the height of every row, more than 0; got {$this->rowHeight}.");
        }

        [$above, $below] = $this->spacers();

        return ScrollView::make($this->spacing, $this->padding)
            ->onScroll(fn (ScrollPosition $at) => $this->scrolled($at))
            ->with(
                $above > 0 ? Column::make()->key('lazy-above')->minSize(0, $above) : null,
                Children::slot(),
                $below > 0 ? Column::make()->key('lazy-below')->minSize(0, $below) : null,
            );
    }

    /**
     * The view moved: ask for the rows now in view, and for more near the end.
     */
    public function scrolled(ScrollPosition $at): void
    {
        if ($this->count > 0) {
            [$first, $last] = $this->inView($at);
            if (($first !== $this->first || $last !== $this->last) && ! is_null($this->onWindow)) {
                ($this->onWindow)($first, $last);
            }
        }

        if ($at->y + $at->height >= $this->nearEnd && ! is_null($this->onNearEnd)) {
            ($this->onNearEnd)();
        }
    }

    /**
     * The rows from first to last in view, overscan beyond each edge, inside 0..count-1. The
     * content is the padding and every row, so a fraction of it is a place along the rows.
     *
     * @return array{int, int}
     */
    public function inView(ScrollPosition $at): array
    {
        $padding = Insets::of($this->padding);
        $unit = $this->rowHeight + $this->spacing;
        $content = $padding->top + $this->count * $unit - $this->spacing + $padding->bottom;
        $top = $at->y * $content - $padding->top;
        $bottom = ($at->y + $at->height) * $content - $padding->top;

        $first = max(0, (int) floor($top / $unit) - $this->overscan);
        $last = min($this->count - 1, (int) floor($bottom / $unit) + $this->overscan);

        return [min($first, $last), $last];
    }

    /**
     * The spacers' heights: the rows before first and after last, each less the one gap the
     * scroll view puts between a spacer and the row beside it. 0 for none.
     *
     * @return array{int, int}
     */
    private function spacers(): array
    {
        $unit = $this->rowHeight + $this->spacing;
        $first = max(0, $this->first);
        $after = max(0, $this->count - 1 - max($this->last, $first - 1));

        return [$first > 0 ? $first * $unit - $this->spacing : 0, $after > 0 ? $after * $unit - $this->spacing : 0];
    }
}
