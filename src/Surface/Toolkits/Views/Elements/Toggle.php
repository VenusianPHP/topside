<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKToggle;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;

/**
 * An on/off switch. Give it the state it shows, and a handler that keeps that state:
 *
 *     Toggle::make(on: $this->dark)->onToggle(fn (bool $on) => $this->dark = $on)
 */
class Toggle extends Element
{
    final public function __construct(protected readonly bool $on = false) {}

    public static function make(bool $on = false): static
    {
        return new static($on);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->toggle($name, $this->on);
    }

    public function kind(): string
    {
        return 'toggle';
    }

    /**
     * Run $handler when the user flips it, with the new state and the Toggled mail.
     *
     * @param Closure(bool, Toggled): mixed $handler
     */
    public function onToggle(Closure $handler): static
    {
        return $this->on('toggle', static fn (Toggled $mail) => $handler($mail->on, $mail));
    }

    protected function tracked(): array
    {
        return ['on'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'on' => fn (TKToggle $p, bool $v) => $p->setOn($v),
        ];
    }

    protected function valueOf(string $prop): mixed
    {
        return $prop === 'on' ? $this->on : parent::valueOf($prop);
    }

    protected function initial(string $prop): mixed
    {
        return $prop === 'on' ? $this->on : parent::initial($prop);
    }
}
