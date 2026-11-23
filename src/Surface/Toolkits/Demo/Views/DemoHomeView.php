<?php

namespace Surface\Toolkits\Demo\Views;

use Surface\Toolkits\Views\Justify;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\Elements\Row;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Toggle;
use Surface\Toolkits\Views\Elements\Element;
use Surface\Toolkits\Views\Elements\Checkbox;
use Surface\Toolkits\Views\Elements\Dropdown;
use Surface\Contracts\Toolkits\UI\Align;

/**
 * The page a new application opens on "/": two cards in the middle of the window.
 *
 *  - A counter: click the button and the label counts the clicks. The handler changes
 *    $clicks, and the label follows because render() reads it.
 *  - Settings: a check box, a switch and a dropdown, each showing a property and keeping it
 *    with its handler; the line under them reads all three back.
 *
 * Card is a component of its own (Card.php): what is inside ->with() goes where its render()
 * puts Children::slot().
 */
class DemoHomeView extends Component
{
    public const array PLANETS = ['Mercury', 'Venus', 'Earth', 'Mars', 'Jupiter', 'Saturn'];

    public int $clicks = 0;

    public bool $subscribed = false;

    public bool $notifications = true;

    public int $planet = 1;

    public function clicked(): void
    {
        $this->clicks++;
    }

    public function render(): Element
    {
        return Column::make(spacing: 16)->justify(Justify::CENTER)->with(
            Card::make(title: 'Venusian Topside', width: 340)->align(Align::CENTER)->with(
                Label::make(match ($this->clicks) {
                    0 => 'Not clicked yet',
                    1 => 'Clicked 1 time',
                    default => "Clicked {$this->clicks} times",
                }),
                Button::make('Click me')->onClick($this->clicked(...))->align(Align::START),
            ),
            Card::make(title: 'Settings', width: 340)->align(Align::CENTER)->with(
                Checkbox::make('Subscribe to the newsletter', checked: $this->subscribed)
                    ->onToggle(fn (bool $on) => $this->subscribed = $on),
                Row::make(spacing: 8)->with(
                    Toggle::make(on: $this->notifications)->onToggle(fn (bool $on) => $this->notifications = $on),
                    Label::make('Notifications')->align(Align::START, Align::CENTER),
                ),
                Row::make(spacing: 8)->with(
                    Label::make('Home planet')->align(Align::START, Align::CENTER),
                    Dropdown::make(self::PLANETS, selected: $this->planet)->onChange(fn (int $index) => $this->planet = $index),
                ),
                Label::make(sprintf(
                    '%s, notifications %s, from %s.',
                    $this->subscribed ? 'Subscribed' : 'Not subscribed',
                    $this->notifications ? 'on' : 'off',
                    self::PLANETS[$this->planet],
                )),
            ),
        );
    }
}
