<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKButton;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * A push button.
 */
class Button extends Element
{
    final public function __construct(protected readonly string $label) {}

    public static function make(string $label): static
    {
        return new static($label);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->button($name, $this->label);
    }

    public function kind(): string
    {
        return 'button';
    }

    /**
     * Run $handler when the user clicks the button. It is given the ButtonClicked mail; a
     * method that takes nothing ignores it: ->onClick($this->save(...)).
     */
    public function onClick(Closure $handler): static
    {
        return $this->on('click', $handler);
    }

    protected function tracked(): array
    {
        return ['label'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'label' => fn (TKButton $p, string $v) => $p->setLabel($v),
        ];
    }

    protected function valueOf(string $prop): mixed
    {
        return $prop === 'label' ? $this->label : parent::valueOf($prop);
    }

    protected function initial(string $prop): mixed
    {
        return $prop === 'label' ? $this->label : parent::initial($prop);
    }
}
