<?php

namespace Venusian\Topside\Tests\Fakes\Views;

use Surface\Toolkits\View;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Element;

/**
 * A view whose button puts another view on screen, as a handler that navigates does.
 */
class LeavingView extends Component
{
    public int $renders = 0;

    public function leave(): void
    {
        View::render(ScriptedView::class);
    }

    public function render(): Element
    {
        $this->renders++;

        return Column::make()->with(Button::make('Leave')->onClick($this->leave(...)));
    }
}
