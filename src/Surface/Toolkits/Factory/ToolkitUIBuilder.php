<?php

namespace Surface\Toolkits\Factory;

use Closure;
use Surface\Contracts\Toolkits\Field;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;
use Surface\Contracts\Toolkits\Primitives\Align;
use Surface\Contracts\Toolkits\Primitives\ImageScaling;
use Surface\Contracts\Toolkits\Primitives\TKColumn;
use Surface\Contracts\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Primitives\TKRow;
use Surface\Contracts\Toolkits\Primitives\TKTransition;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\FontWeight;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\Support\Live;
use Surface\Toolkits\Support\Reveals;
use Surface\Toolkits\Support\Tween;
use Surface\Toolkits\Support\ViewActions;

/**
 * Builds a view's layout from toolkit primitives. Each call adds to $group.
 */
class ToolkitUIBuilder implements ViewBuilder
{
    /**
     * @param TKPrimitiveGroup        $group   where calls build
     * @param TKColumn|null           $root    the view's root column, where an overlay goes
     * @param TKColumn|null           $content the view's content column, hidden while an overlay shows
     * @param TKColumn|null           $frame   what hide() hides when that is not $group: an overlay's whole pane
     * @param Reveals|null            $reveals where widgets built hidden wait for the window, so their appear
     *                                         transitions play when they show
     */
    public function __construct(
        protected TKPrimitiveGroup $group,
        protected ?TKColumn $root = null,
        protected ?TKColumn $content = null,
        protected ?TKColumn $frame = null,
        protected ?Reveals $reveals = null,
    ) {}

    /** A builder for $group in the same view: the same root, content and reveal list. */
    protected function inside(TKPrimitiveGroup $group): self
    {
        return new self($group, $this->root, $this->content, null, $this->reveals);
    }

    public function column(string $name, int $spacing = 0, int $padding = 0): ViewBuilder
    {
        return $this->inside($this->group->column($name, $spacing, $padding));
    }

    public function row(string $name, int $spacing = 0, int $padding = 0): ViewBuilder
    {
        return $this->inside($this->group->row($name, $spacing, $padding));
    }

    /**
     * A Closure $text is read when the label is built and again on each Live::refresh(): the label
     * shows the new text when it differs.
     */
    public function label(string $name, string|Closure $text, ?string $role = null): void
    {
        $label = $this->group->label($name, '');
        $label->setText($text instanceof Closure
            ? app(Live::class)->watch(fn (): string => (string) $text(), fn (string $next) => $label->setText($next))
            : $text);

        match ($role) {
            'title' => $label->setFont(new FontSpec(24, FontWeight::BOLD)),
            'display' => $label->setFont(new FontSpec(56, FontWeight::BOLD))->setAlignment(TextAlignment::CENTER)->align(Align::CENTER, Align::CENTER),
            'subtitle' => $label->setFont(new FontSpec(20))->setAlignment(TextAlignment::CENTER)->align(Align::CENTER, Align::CENTER),
            'body' => $label->setWrap(true),
            'footnote' => $label->setFont(new FontSpec(11))->setTextColor(Color::rgba(150, 150, 150, 1.0)),
            default => null,
        };
    }

    public function card(string $name, string $title): ViewBuilder
    {
        $card = $this->group->column($name, 8, 16);
        $card->fill(true, false);
        $card->minSize(160, 0);
        $card->setBackground(Color::rgba(128, 128, 128, 0.14));
        $card->setCornerRadius(12);

        // named so that a view's own 'title' inside the card does not meet it
        $card->label('card-title', $title)->setFont(new FontSpec(15, FontWeight::BOLD));

        return $this->inside($card);
    }

    public function link(string $name, string $text, string $url): void
    {
        $this->group->link($name, $text, $url);
    }

    /** Only along its parent's axis: a spacer that also fills across makes AppKit and GTK grow the parent. */
    public function spacer(string $name): void
    {
        $this->group instanceof TKRow
            ? $this->group->column($name)->fill(true, false)
            : $this->group->column($name)->fill(false, true);
    }

    public function scroll(string $name): ViewBuilder
    {
        $scroll = $this->group->scrollView($name);
        $scroll->setScrollbars(false, true);
        $scroll->fill();
        $column = $scroll->column('content', 12);
        $column->align(Align::FILL, Align::START);

        return $this->inside($column);
    }

    public function hide(): void
    {
        if (is_null($this->frame)) {
            $this->group->setVisible(false);

            return;
        }

        // An overlay: it goes at once, and the content fades back into its place.
        $this->frame->setVisible(false);
        $this->content?->appear(TKTransition::FADE, 0.25)->setVisible(true);
    }

    /**
     * $onClick is what a click does: a Closure runs, [Controller::class, 'method'] calls that method,
     * and a string is a route name the Navigator goes to. Each is given the ButtonClicked mail.
     */
    public function button(string $name, string $label, Closure|array|string|null $onClick = null, bool $destructive = false): void
    {
        $button = $this->group->button($name, $label);

        if ($destructive) {
            $button->setTextColor(Color::rgba(255, 69, 58, 1.0));
        }
        if (! is_null($onClick)) {
            app(ViewActions::class)->bind($button->uuid(), $onClick);
        }
    }

    /** $onSubmit is what Return does, as button()'s $onClick. The Field reads and empties what was typed. */
    public function input(string $name, string $placeholder = '', bool $secret = false, Closure|array|string|null $onSubmit = null): Field
    {
        $input = $this->group->textInput($name, '', $placeholder, $secret);
        $input->fill(true, false);

        if (! is_null($onSubmit)) {
            app(ViewActions::class)->bind($input->uuid(), $onSubmit);
        }

        return new Field(fn (): string => $input->value(), fn () => $input->setValue(''));
    }

    public function image(string $name, string $file, int $size): void
    {
        $this->icon($this->group, $name, $file, $size)->align(Align::CENTER);
    }

    /** A fixed gives an image an exact frame; minSize() alone lets it grow to the file's size. */
    protected function icon(TKPrimitiveGroup $in, string $name, string $file, int $size): TKPrimitiveGroup
    {
        $box = $in->fixed($name);
        $box->minSize($size, $size);
        $box->at(0, 0, $size, $size)->image('image', $file)->setScaling(ImageScaling::FIT);

        return $box;
    }

    /** A column with an optional bold title, then the rounded panel that option() and detail() rows go in. A Closure $title is read one time. */
    public function group(string $name, string|Closure|null $title = null): ViewBuilder
    {
        // A centred column at the macOS Settings content width.
        $wrap = $this->group->column($name, 6);
        $wrap->align(Align::CENTER, Align::START);
        $wrap->minSize(560, 0);

        if (! is_null($title)) {
            // Indented to line up with the rows' content.
            $row = $wrap->row('title-row');
            $row->column('indent')->minSize(10, 0);
            $row->label('title', $title instanceof Closure ? (string) $title() : $title)
                ->setFont(new FontSpec(13, FontWeight::BOLD))
                ->setAlignment(TextAlignment::LEFT);
        }

        $panel = $wrap->column('options');
        $panel->align(Align::FILL);
        $panel->setBackground(Color::rgba(128, 128, 128, 0.14));
        $panel->setCornerRadius(10);

        return $this->inside($panel);
    }

    /** A combo box. TK's combo box has no placeholder, so $placeholder is not shown. */
    public function search(string $name, string $value, string $placeholder, Closure $matches, Closure|array|string|null $onSubmit = null): Field
    {
        $box = $this->group->comboBox($name, $value);
        $box->fill(true, false);

        // Typing fills the list. An empty field, or one that already holds the only match, closes it.
        app(ViewActions::class)->onChange($box->uuid(), function (object $mail) use ($box, $matches): void {
            if (! $mail instanceof TextChanged) {
                return;
            }
            $query = trim($mail->value);
            $found = $query === '' ? [] : $matches($query);
            $box->setCompletions($found === [$query] ? [] : $found);
        });
        if (! is_null($onSubmit)) {
            app(ViewActions::class)->bind($box->uuid(), $onSubmit);
        }

        return new Field(fn (): string => $box->value(), fn () => $box->setValue(''));
    }

    /**
     * The toolkit's segmented control centred over one column per item. A user's choice and the
     * returned Closure swap the pages with a cross-fade; the Closure also moves the control, which
     * posts nothing.
     */
    public function tabs(string $name, array $items, Closure $page): Closure
    {
        $wrap = $this->group->column($name, 16);
        $wrap->fill();
        $labels = array_map(fn (string|Closure $item): string => is_string($item) ? $item : (string) $item(), array_values($items));
        $control = $wrap->segmentedControl('toolbar', $labels, 0);
        $control->align(Align::CENTER, Align::START);
        $stack = $wrap->column('pages');
        $stack->fill();

        $pages = [];
        foreach (array_values($items) as $i => $item) {
            is_string($item) || app(Live::class)->watch($item, fn (string $label) => $control->setSegment($i, $label));
            $content = $stack->column('page-'.$i, 12);
            $content->fill();
            $content->setVisible($i === 0);
            $pages[] = $content;
            $page($this->inside($content), $i);
        }

        $shown = 0;
        $swap = function (int $i) use (&$shown, $pages, $stack): void {
            if ($i === $shown) {
                return;
            }
            [$old, $new] = [$pages[$shown], $pages[$i]];
            $shown = $i;
            $key = 'tabs.'.$stack->uuid();
            Tween::run($key, 0.12, fn (float $t) => $old->setOpacity(Tween::between(1.0, 0.0, $t)), function () use ($key, $old, $new): void {
                $old->setVisible(false)->setOpacity(1.0);
                $new->setOpacity(0.0)->setVisible(true);
                Tween::run($key, 0.2, fn (float $t) => $new->setOpacity(Tween::between(0.0, 1.0, $t)));
            });
        };
        app(ViewActions::class)->onChange($control->uuid(), function (object $mail) use ($swap): void {
            $mail instanceof SelectionChanged && $swap($mail->index);
        });

        return function (int $i) use ($control, $swap): void {
            $control->select($i);
            $swap($i);
        };
    }

    /**
     * Toolkits do not clip a column to a height, and minSize() only sets a floor. So closing fades
     * the body out, holds its height with the floor, hides it, then lowers the floor to nothing.
     * Opening raises the floor to the body's height, then shows the body and fades it in.
     */
    public function reveal(string $name, Closure $shown): ViewBuilder
    {
        $outer = $this->group->column($name);
        $outer->align(Align::FILL, Align::START);
        $body = $outer->column('body', 12);
        $body->align(Align::FILL, Align::START);

        $open = (bool) app(Live::class)->watch($shown, fn (bool $open) => self::toggle($outer, $body, $open));
        if (! $open) {
            self::measureClosed($body);
        }

        return $this->inside($body);
    }

    /** @var array<string, int> the open height of each reveal's body, by uuid: what opening grows the floor to */
    private static array $heights = [];

    private static function toggle(TKColumn $outer, TKColumn $body, bool $open): void
    {
        $key = 'reveal.'.$outer->uuid();
        app('event-loop')->forget("{$key}.measure");

        if (! $open) {
            $height = $body->isVisible() ? $body->size()[1] : 0;
            $height > 0 && self::$heights[$body->uuid()] = $height;
            $outer->minSize(0, $height);
            $from = $body->opacity();
            Tween::run($key, 0.2, fn (float $t) => $body->setOpacity(Tween::between($from, 0.0, $t)), function () use ($key, $outer, $body, $height): void {
                $body->setVisible(false);
                Tween::run($key, 0.3, fn (float $t) => $outer->minSize(0, (int) round(Tween::between($height, 0, $t))));
            });

            return;
        }

        $height = self::$heights[$body->uuid()] ?? 0;
        $from = (int) ($outer->size()[1] ?? 0);
        $fadeIn = function () use ($key, $outer, $body): void {
            $body->setOpacity(0.0)->setVisible(true);
            Tween::run($key, 0.25, fn (float $t) => $body->setOpacity(Tween::between(0.0, 1.0, $t)), fn () => $outer->minSize(0, 0));
        };
        // A body that was never measured opens at once and only fades. measureClosed() makes that rare.
        $height > 0
            ? Tween::run($key, 0.3, fn (float $t) => $outer->minSize(0, (int) round(Tween::between($from, $height, $t))), $fadeIn)
            : $fadeIn();
    }

    /**
     * A body built closed has never been laid out, so its open height is not known. It stays laid
     * out but transparent until the toolkit gives it a height (its page may be hidden until shown),
     * then it is hidden.
     */
    private static function measureClosed(TKColumn $body): void
    {
        $body->setOpacity(0.0);
        // The same key toggle() forgets: the parent is the reveal's outer column.
        $key = 'reveal.'.$body->parent()?->uuid().'.measure';
        app('event-loop')->every(0.25, function () use ($body, $key): void {
            $height = $body->size()[1];
            if ($height > 0) {
                self::$heights[$body->uuid()] = $height;
                $body->setVisible(false);
                app('event-loop')->forget($key);
            }
        }, $key);
    }

    /** A clickable row with a chevron at its right edge, and a separator above it when it is not its group's first. */
    public function option(string $name, string $label, ?string $icon = null, Closure|array|string|null $onClick = null): void
    {
        if ($this->group->children() !== []) {
            $this->group->separator("{$name}-divider");
        }

        $row = $this->group->row($name, 10, 8);
        $row->align(Align::FILL);

        if (! is_null($icon)) {
            $this->icon($row, 'icon-box', $icon, 22)->align(Align::START, Align::CENTER);
        }

        $row->label('label', $label)->setAlignment(TextAlignment::LEFT)->align(Align::START, Align::CENTER);
        $row->column('gap')->fill(true, false);    // the spare width: the chevron sits at the right edge
        $row->label('chevron', '›')
            ->setFont(new FontSpec(17))
            ->setTextColor(Color::rgba(160, 160, 160, 1.0))
            ->align(Align::END, Align::CENTER);

        if (! is_null($onClick)) {
            $row->setClickable();    // the row then posts GroupClicked
            app(ViewActions::class)->bind($row->uuid(), $onClick);
        }
    }

    /** A row that shows a label at the left and its value, in grey, at the right. */
    public function detail(string $name, string $label, string $value): void
    {
        if ($this->group->children() !== []) {
            $this->group->separator("{$name}-divider");
        }

        $row = $this->group->row($name, 10, 8);
        $row->align(Align::FILL);
        $row->label('label', $label)->setAlignment(TextAlignment::LEFT)->align(Align::START, Align::CENTER);
        $row->column('gap')->fill(true, false);
        $row->label('value', $value)
            ->setTextColor(Color::rgba(150, 150, 150, 1.0))
            ->align(Align::END, Align::CENTER);
    }

    /** A round "?" button, 22 points across. */
    public function helpButton(string $name, Closure|array|string $onClick): void
    {
        // A fixed gives the button an exact 22-point frame; minSize() alone lets GTK's padding widen it.
        $box = $this->group->fixed("{$name}-box");
        $box->minSize(22, 22);
        $box->align(Align::END, Align::CENTER);

        $button = $box->at(0, 0, 22, 22)->button($name, '?')->setBordered(false);
        $button->setFont(new FontSpec(13, FontWeight::BOLD));
        $button->setBackground(Color::rgba(128, 128, 128, 0.30));
        $button->setCornerRadius(11);

        app(ViewActions::class)->bind($button->uuid(), $onClick);
    }

    /**
     * Toolkits have no layers here, so the overlay takes the content's place while it shows: a
     * column over the whole view with a dimmed background, and a card centred in it.
     *
     * @throws ToolkitRuntimeException When the builder was made without the view's root and content columns.
     */
    public function overlay(string $name): ViewBuilder
    {
        if (is_null($this->root) || is_null($this->content)) {
            throw new ToolkitRuntimeException("ViewBuilder::overlay('{$name}') needs the view's root and content columns: build the view through the tree driver.");
        }

        $overlay = $this->root->column($name);
        $overlay->fill();
        $overlay->setBackground(Color::rgba(0, 0, 0, 0.35));

        // Two columns that take the spare height, one above and one below, centre the card.
        $overlay->column('above')->fill(false, true);

        $card = $overlay->column('card', 12, 24);
        $card->align(Align::CENTER);
        $card->minSize(420, 0);
        $card->setBackground(Color::rgba(128, 128, 128, 0.22));

        $overlay->column('below')->fill(false, true);

        $this->content->setVisible(false);

        // Built hidden: a transition plays only on the change from hidden to visible. Reveals shows
        // both when the window is presented, or at once if it already is.
        $overlay->appear(TKTransition::FADE, 0.25);
        $card->appear(TKTransition::SLIDE_UP, 0.3);
        $overlay->setVisible(false);
        $card->setVisible(false);
        $this->reveals?->add($overlay);
        $this->reveals?->add($card);

        return new self($card, $this->root, $this->content, $overlay, $this->reveals);
    }
}
