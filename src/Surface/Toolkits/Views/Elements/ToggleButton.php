<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKToggleButton;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * A button that stays pressed.
 *
 *     <ToggleButton :pressed="$bold" @toggle="fn ($on) => $this->bold = $on">Bold</ToggleButton>
 */
class ToggleButton extends Element
{
    final public function __construct(
        protected readonly string $label,
        protected readonly bool $pressed = false,
    ) {}

    public static function make(string $label, bool $pressed = false): static
    {
        return new static($label, $pressed);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->toggleButton($name, $this->label, $this->pressed);
    }

    public function kind(): string
    {
        return 'toggleButton';
    }

    /**
     * Run $handler when the user presses it in or out, with the state and the mail.
     *
     * @param Closure(bool, Toggled): mixed $handler
     */
    public function onToggle(Closure $handler): static
    {
        return $this->on('toggle', static fn (Toggled $mail) => $handler($mail->on, $mail));
    }

    protected function made(): array
    {
        return ['label' => $this->label, 'pressed' => $this->pressed];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'label' => fn (TKToggleButton $p, string $v) => $p->setLabel($v),
            'pressed' => fn (TKToggleButton $p, bool $v) => $p->setPressed($v),
        ];
    }
}
