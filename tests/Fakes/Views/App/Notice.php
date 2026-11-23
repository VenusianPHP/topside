<?php

namespace Venusian\Topside\Tests\Fakes\Views\App;

use Closure;
use Surface\Toolkits\View;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Element;
use Surface\Toolkits\Views\Elements\Children;

/** An application's composed component, as it would sit in App\Views. */
class Notice extends Component
{
    public string $title = '';

    public int $width = 0;

    public ?string $badge = null;

    public ?Closure $onClosed = null;

    public int $opened = 0;

    public function render(): Element
    {
        return Column::make()->minSize($this->width, 0)->with(
            Label::make("{$this->title}:{$this->opened}"),
            is_null($this->badge) ? null : View::component('badge', text: $this->badge),
            Button::make('More')->onClick(fn () => $this->opened++),
            Button::make('Close')->onClick(fn () => is_null($this->onClosed) ? null : ($this->onClosed)()),
            Children::slot(),
        );
    }
}
