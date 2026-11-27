<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TKFlow;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Children side by side, each at its own size, wrapping onto the next line where the width
 * runs out.
 *
 *     <Flow spacing="8">
 *         @foreach ($tags as $tag)
 *             <Button :key="$tag" @click="search($tag)">{{ $tag }}</Button>
 *         @endforeach
 *     </Flow>
 */
class Flow extends Container
{
    protected readonly Insets $padding;

    /**
     * @throws ViewException When the padding has a negative side or cannot be read.
     */
    final public function __construct(
        protected readonly int $spacing = 0,
        Insets|int|string|array $padding = 0,
    ) {
        $this->padding = self::insetsOf($padding, 'padding');
    }

    /**
     * @param int $spacing points between children on a line, and between lines
     * @param Insets|int|string|list<int> $padding round the children, as CSS writes it
     */
    public static function make(int $spacing = 0, Insets|int|string|array $padding = 0): static
    {
        return new static($spacing, $padding);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->flow($name, $this->spacing, $this->padding);
    }

    public function kind(): string
    {
        return 'flow';
    }

    protected function made(): array
    {
        return ['spacing' => $this->spacing, 'padding' => $this->padding];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'spacing' => fn (TKFlow $p, int $v) => $p->setSpacing($v),
            'padding' => fn (TKFlow $p, Insets $v) => $p->setPadding($v),
        ];
    }
}
