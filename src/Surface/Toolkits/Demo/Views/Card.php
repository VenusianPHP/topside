<?php

namespace Surface\Toolkits\Demo\Views;

use Surface\NutsAndBolts\Color;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Element;
use Surface\Toolkits\Views\Elements\Children;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\FontWeight;

/**
 * A component made of other primitives: there is no card primitive, only a padded column
 * with a tint and rounded corners, a bold title, and whatever its user nests inside it.
 *
 *     Card::make(title: 'Counter')->with(Label::make('...'), Button::make('...'))
 */
class Card extends Component
{
    public string $title = '';

    /**
     * The narrowest the card gets. Content wider than this widens it, so a card whose content
     * changes width (a line of text that changes) should be given at least its widest.
     */
    public int $width = 240;

    public function render(): Element
    {
        return Column::make(spacing: 8, padding: 16)
            ->background(Color::rgba(128, 128, 128, 0.14))
            ->cornerRadius(12)
            ->minSize($this->width, 0)
            ->with(
                $this->title === '' ? null : Label::make($this->title)->font(new FontSpec(15, FontWeight::BOLD)),
                Children::slot(),
            );
    }
}
