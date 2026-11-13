<?php

namespace Surface\Contracts\Toolkits;

use Closure;

interface ViewBuilder
{
    public function row(string $name, int $spacing = 0, int $padding = 0): ViewBuilder;

    public function column(string $name, int $spacing = 0, int $padding = 0): ViewBuilder;

    /**
     * $role names what the text is ('display', 'title', 'body', 'footnote'); each builder decides how that looks.
     * A Closure $text is read again on each Live refresh, and the label then shows the new text.
     */
    public function label(string $name, string|Closure $text, ?string $role = null): void;

    /** $destructive: an action that deletes something, drawn in the danger colour. */
    public function button(string $name, string $label, Closure|array|string|null $onClick = null, bool $destructive = false): void;

    /**
     * One line of text the user types; $secret shows dots. It takes the spare width of its row.
     * Return in it runs $onSubmit. Read what was typed from the Field it gives back.
     */
    public function input(string $name, string $placeholder = '', bool $secret = false, Closure|array|string|null $onSubmit = null): Field;

    /** A square image, $size points a side, centred across a column. */
    public function image(string $name, string $file, int $size): void;

    /** A rounded panel with a bold title: what is built into it goes under the title. Cards in a row share its width. */
    public function card(string $name, string $title): ViewBuilder;

    /** Text that opens $url in the user's browser when clicked. */
    public function link(string $name, string $text, string $url): void;

    /** A rounded panel of options, with an optional title above it (a Closure title as label()). Build its rows with option(). */
    public function group(string $name, string|Closure|null $title = null): ViewBuilder;

    /**
     * A field that searches as the user types: $matches(query) fills the list under it, a pick from
     * the list becomes the value, and Return runs $onSubmit. value() reads the field.
     * @param Closure(string): list<string> $matches
     */
    public function search(string $name, string $value, string $placeholder, Closure $matches, Closure|array|string|null $onSubmit = null): Field;

    /**
     * A toolbar of $items over one page per item, the first shown. A click on an item shows its page
     * with a transition. $page builds each page: fn (ViewBuilder $ui, int $index): void. A Closure
     * item is a label read again on each Live refresh.
     * @param list<string|Closure(): string> $items
     * @return Closure(int): void shows page $index, as a click on its item does
     */
    public function tabs(string $name, array $items, Closure $page): Closure;

    /**
     * A section that opens and closes smoothly: $shown is read now and on each Live refresh. What
     * follows it moves with its height.
     * @param Closure(): bool $shown
     */
    public function reveal(string $name, Closure $shown): ViewBuilder;

    /** One row of a group: an icon, a label and a chevron. A click on the row runs $onClick. */
    public function option(string $name, string $label, ?string $icon = null, Closure|array|string|null $onClick = null): void;

    /** One row of a group with no action: a label, and a grey value at the right edge. */
    public function detail(string $name, string $label, string $value): void;

    /** A scrolling column: what is built into it scrolls up and down when it is taller than the page. */
    public function scroll(string $name): ViewBuilder;

    /** Empty space that takes what its row or column has spare: it pushes what follows to the far end. */
    public function spacer(string $name): void;

    /** A small round button with a question mark, as at the foot of the macOS Settings panes. */
    public function helpButton(string $name, Closure|array|string $onClick): void;

    /**
     * A pane on top of the view's content.
     */
    public function overlay(string $name): ViewBuilder;

    /**
     * Hide what this builder builds into (for an overlay: the whole pane).
     */
    public function hide(): void;
}