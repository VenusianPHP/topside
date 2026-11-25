<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Toolkits\Views\Justify;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\TKColumn as ColumnContract;
use Surface\Contracts\Toolkits\UI\TKRow as RowContract;

/**
 * What a column and a row share: children one after another, a gap between them, padding
 * round them all (each side its own), and where along the axis they gather.
 */
abstract class Stack extends Container
{
    protected Justify $justify = Justify::START;

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
     * @param int $spacing points between one child and the next
     * @param Insets|int|string|list<int> $padding points between the edge and the children, as
     *        CSS writes it: 16, '40 16', '40 16 8' or '40 16 8 0'
     */
    public static function make(int $spacing = 0, Insets|int|string|array $padding = 0): static
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

    protected function setters(): array
    {
        return parent::setters() + [
            'spacing' => fn (ColumnContract|RowContract $p, int $v) => $p->setSpacing($v),
            'padding' => fn (ColumnContract|RowContract $p, Insets $v) => $p->setPadding($v),
        ];
    }

    /**
     * The constructor's spacing and padding are properties like any other, so a change becomes
     * setSpacing() or setPadding(), not a new primitive.
     */
    protected function valueOf(string $prop): mixed
    {
        return match ($prop) {
            'spacing' => $this->spacing,
            'padding' => $this->padding,
            default => parent::valueOf($prop),
        };
    }

    protected function initial(string $prop): mixed
    {
        return in_array($prop, ['spacing', 'padding'], true) ? $this->valueOf($prop) : parent::initial($prop);
    }

    protected function tracked(): array
    {
        return ['spacing', 'padding'];
    }
}
