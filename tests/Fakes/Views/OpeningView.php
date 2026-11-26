<?php

namespace Venusian\Topside\Tests\Fakes\Views;

use Surface\Toolkits\View;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Element;

/**
 * A view that starts its work as it is put on screen, as a page that loads does; with
 * $leave it puts another view on screen instead.
 */
class OpeningView extends Component
{
    public string $said = 'idle';

    public bool $leave = false;

    public int $opened = 0;

    public int $renders = 0;

    /** What mounted() saw: whether the window and its navigation were there. */
    public bool $had_window = false;

    public function mounted(): void
    {
        $this->opened++;
        $this->had_window = $this->window()->isOpen() && ! is_null($this->navigator());
        if ($this->leave) {
            View::render(ScriptedView::class);

            return;
        }
        $this->said = 'loading';
    }

    public function render(): Element
    {
        $this->renders++;

        return Label::make($this->said);
    }
}
