<?php

namespace Venusian\Topside\Tests\Fakes\Views;

use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\Elements\Row;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Element;

/**
 * A component with a prop ($label, the parent's) and state of its own ($count).
 */
class Counter extends Component
{
    public string $label = '';

    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): Element
    {
        return Row::make()->with(
            Label::make("{$this->label}: {$this->count}"),
            Button::make('+')->onClick($this->increment(...)),
        );
    }
}
