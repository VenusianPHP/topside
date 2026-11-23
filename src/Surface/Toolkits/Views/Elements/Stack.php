<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Toolkits\Views\Justify;
use Surface\Contracts\Toolkits\UI\TKColumn as ColumnContract;
use Surface\Contracts\Toolkits\UI\TKRow as RowContract;

/**
 * What a column and a row share: children one after another, a gap between them, a margin
 * around them all, and where along the axis they gather.
 */
abstract class Stack extends Container
{
    protected Justify $justify = Justify::START;

    final public function __construct(
        protected readonly int $spacing = 0,
        protected readonly int $padding = 0,
    ) {}

    /**
     * @param int $spacing points between one child and the next
     * @param int $padding points between the edge and the children, on every side
     */
    public static function make(int $spacing = 0, int $padding = 0): static
    {
        return new static($spacing, $padding);
    }

    /**
     * Where along the axis the children gather when there is room to spare. Done with a
     * spacer before (END), after (none needed for START) or on both sides (CENTER): the same
     * on every toolkit, since every toolkit shares spare room among the children that fill.
     */
    public function justify(Justify $justify): static
    {
        $this->justify = $justify;

        return $this;
    }

    /**
     * The children with the justify spacers, which carry their own keys so that they stay the
     * same primitives from render to render.
     */
    public function children(): array
    {
        $before = in_array($this->justify, [Justify::CENTER, Justify::END], true) ? [Spacer::make()->key("\0justify-before")] : [];
        $after = $this->justify === Justify::CENTER ? [Spacer::make()->key("\0justify-after")] : [];

        return [...$before, ...$this->children, ...$after];
    }

    /**
     * A container takes its padding only when it is built.
     */
    protected function shape(): array
    {
        return ['padding' => $this->padding];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'spacing' => fn (ColumnContract|RowContract $p, int $v) => $p->setSpacing($v),
        ];
    }

    /**
     * The constructor's spacing is a property like any other, so a change becomes setSpacing().
     */
    protected function valueOf(string $prop): mixed
    {
        return $prop === 'spacing' ? $this->spacing : parent::valueOf($prop);
    }

    protected function initial(string $prop): mixed
    {
        return $prop === 'spacing' ? $this->spacing : parent::initial($prop);
    }

    protected function tracked(): array
    {
        return ['spacing'];
    }
}
