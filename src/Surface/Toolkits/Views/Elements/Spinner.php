<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\TKSpinner;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Something is happening. It spins while it is there.
 *
 *     <Spinner :spinning="$loading" />
 */
class Spinner extends Element
{
    final public function __construct(
        protected readonly bool $spinning = true,
    ) {}

    public static function make(bool $spinning = true): static
    {
        return new static($spinning);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->spinner($name, $this->spinning);
    }

    public function kind(): string
    {
        return 'spinner';
    }

    protected function made(): array
    {
        return ['spinning' => $this->spinning];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'spinning' => fn (TKSpinner $p, bool $v) => $v ? $p->start() : $p->stop(),
        ];
    }
}
