<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKCheckbox;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;

/**
 * A labelled check box. Give it the state it shows, and a handler that keeps that state:
 *
 *     Checkbox::make('Remember me', checked: $this->remember)
 *         ->onToggle(fn (bool $on) => $this->remember = $on)
 */
class Checkbox extends Element
{
    final public function __construct(
        protected readonly string $label,
        protected readonly bool $checked = false,
    ) {}

    public static function make(string $label, bool $checked = false): static
    {
        return new static($label, $checked);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->checkbox($name, $this->label, $this->checked);
    }

    public function kind(): string
    {
        return 'checkbox';
    }

    /**
     * Run $handler when the user toggles it, with the new state and the Toggled mail.
     *
     * @param Closure(bool, Toggled): mixed $handler
     */
    public function onToggle(Closure $handler): static
    {
        return $this->on('toggle', static fn (Toggled $mail) => $handler($mail->on, $mail));
    }

    protected function tracked(): array
    {
        return ['label', 'checked'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'label' => fn (TKCheckbox $p, string $v) => $p->setLabel($v),
            'checked' => fn (TKCheckbox $p, bool $v) => $p->setChecked($v),
        ];
    }

    protected function valueOf(string $prop): mixed
    {
        return match ($prop) {
            'label' => $this->label,
            'checked' => $this->checked,
            default => parent::valueOf($prop),
        };
    }

    protected function initial(string $prop): mixed
    {
        return in_array($prop, ['label', 'checked'], true) ? $this->valueOf($prop) : parent::initial($prop);
    }
}
