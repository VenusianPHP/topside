<?php

namespace Surface\Toolkits\Factory;

use Closure;
use Surface\Contracts\Toolkits\Field;
use Surface\Contracts\Toolkits\Primitives\Align;
use Surface\Contracts\Toolkits\Primitives\ImageScaling;
use Surface\Contracts\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Primitives\TKRow;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\FontWeight;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\Support\Live;
use Surface\Toolkits\Support\ViewActions;

/**
 * Builds a view's layout from toolkit primitives. Each call adds to $group.
 */
class ToolkitUIBuilder implements ViewBuilder
{
    /**
     * @param TKPrimitiveGroup $group where calls build
     */
    public function __construct(
        protected TKPrimitiveGroup $group,
    ) {}

    public function column(string $name, int $spacing = 0, int $padding = 0): ViewBuilder
    {
        return new self($this->group->column($name, $spacing, $padding));
    }

    public function row(string $name, int $spacing = 0, int $padding = 0): ViewBuilder
    {
        return new self($this->group->row($name, $spacing, $padding));
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

        return new self($card);
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

        return new self($column);
    }

    public function hide(): void
    {
        $this->group->setVisible(false);
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

        return new self($panel);
    }

    public function search(string $name, string $value, string $placeholder, Closure $matches, Closure|array|string|null $onSubmit = null): Field
    {
        $this->later(__FUNCTION__);
    }

    public function tabs(string $name, array $items, Closure $page): Closure
    {
        $this->later(__FUNCTION__);
    }

    public function reveal(string $name, Closure $shown): ViewBuilder
    {
        $this->later(__FUNCTION__);
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

    public function overlay(string $name): ViewBuilder
    {
        $this->later(__FUNCTION__);
    }

    /**
     * @throws ToolkitRuntimeException Always: this builder does not have the method yet.
     */
    protected function later(string $method): never
    {
        throw new ToolkitRuntimeException("ViewBuilder::{$method}() is not in Topside's ToolkitBuilder yet.");
    }
}
