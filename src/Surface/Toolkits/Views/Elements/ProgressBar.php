<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKProgressBar;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * How far along something is, 0 to 1. With no value it shows that this is not known yet.
 *
 *     <ProgressBar :value="$done / $total" />
 */
class ProgressBar extends Element
{
    final public function __construct(
        protected readonly ?float $value = null,
    ) {}

    /**
     * @param float|null $value 0 to 1; null when not known
     */
    public static function make(?float $value = null): static
    {
        return new static($value);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->progressBar($name, $this->value);
    }

    public function kind(): string
    {
        return 'progressBar';
    }

    protected function made(): array
    {
        return ['value' => $this->value];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'value' => fn (TKProgressBar $p, ?float $v) => $p->setFraction($v),
        ];
    }
}
