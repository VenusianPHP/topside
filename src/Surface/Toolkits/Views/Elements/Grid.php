<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Toolkits\Views\Mounted;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TKGrid;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Children in so many columns, filling each row and then the next. A child takes more than
 * one column with span="2"; one that is hidden or left out takes none.
 *
 *     <Grid columns="2" spacing="8">
 *         <Label>Name</Label> <TextInput />
 *         <Button span="2">Save</Button>
 *     </Grid>
 */
class Grid extends Container
{
    /**
     * @throws ViewException When there is not a column.
     */
    protected readonly Insets $padding;

    final public function __construct(
        protected readonly int $columns = 2,
        protected readonly int $spacing = 0,
        Insets|int|string|array $padding = 0,
    ) {
        if ($columns < 1) {
            throw new ViewException("A Grid has 1 column or more, got {$columns}.");
        }
        $this->padding = self::insetsOf($padding, 'padding');
    }

    /**
     * @param Insets|int|string|list<int> $padding round the cells, as CSS writes it
     */
    public static function make(int $columns = 2, int $spacing = 0, Insets|int|string|array $padding = 0): static
    {
        return new static($columns, $spacing, $padding);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->grid($name, $this->spacing, $this->padding);
    }

    public function kind(): string
    {
        return 'grid';
    }

    /**
     * Give each child its cell, once they are all there and in their order. Those that take
     * none are parked in rows below.
     *
     * @param list<Mounted> $children
     */
    public function settle(TKPrimitive $primitive, ?Element $before, array $children): void
    {
        $cells = [];
        $parked = [];
        [$row, $column] = [0, 0];

        foreach ($children as $child) {
            if (! $child->primitive->isVisible()) {
                $parked[] = $child->primitive;
                continue;
            }
            $span = min($this->columns, max(1, $child->described()->valueOf('span')));
            if ($column + $span > $this->columns) {
                [$row, $column] = [$row + 1, 0];
            }
            $cells[] = [$child->primitive, Placement::cell($row, $column, columnSpan: $span)];
            $column += $span;
        }

        $below = $column === 0 ? $row : $row + 1;
        foreach ($parked as $index => $hidden) {
            $cells[] = [$hidden, Placement::cell($below + $index, 0)];
        }

        /** @var TKGrid $primitive */
        $primitive->arrangeCells($cells);
    }

    protected function made(): array
    {
        return ['spacing' => $this->spacing, 'padding' => $this->padding];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'spacing' => fn (TKGrid $p, int $v) => $p->setSpacing($v),
            'padding' => fn (TKGrid $p, Insets $v) => $p->setPadding($v),
        ];
    }
}
