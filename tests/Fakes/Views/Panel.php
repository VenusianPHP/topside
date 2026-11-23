<?php

namespace Venusian\Topside\Tests\Fakes\Views;

use Closure;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Element;
use Surface\Toolkits\Views\Elements\Children;

/**
 * A component with a slot, and a Close button that tells its parent through $onClose.
 * $asLabel switches its root's kind, for "a new root keeps the component".
 */
class Panel extends Component
{
    public string $heading = '';

    public ?Closure $onClose = null;

    public bool $asLabel = false;

    public int $renders = 0;

    /** Panels built in this process. */
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }

    public function close(): void
    {
        ($this->onClose)?->__invoke();
    }

    public function render(): Element
    {
        $this->renders++;

        if ($this->asLabel) {
            return Label::make($this->heading);
        }

        return Column::make()->with(
            Label::make($this->heading),
            Children::slot(),
            Button::make('Close')->onClick($this->close(...)),
        );
    }
}
