<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TKTable;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TableColumn;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\RowSelected;

/**
 * Rows in columns. Rows are arrays, by key or in the columns' order. With no columns given
 * they are the first row's keys. It needs no state for the selection.
 *
 *     <Table :rows="$crew" :columns="['name' => 'Name', 'age' => 'Age']" @select="fn ($row) => $this->who = $row" />
 */
class Table extends Element
{
    /** @var list<TableColumn> */
    protected readonly array $columns;

    /** @var list<array<string, string>> */
    protected readonly array $rows;

    /** @var list<array> as written */
    protected readonly array $given;

    /**
     * @throws ViewException For a row that is no array, or a cell that is neither text, a number nor null.
     */
    final public function __construct(array $rows = [], array $columns = [], protected readonly ?int $selected = null)
    {
        $given = array_values($rows);
        foreach ($given as $index => $row) {
            if (! is_array($row)) {
                throw new ViewException("Row {$index} of a Table is an array of its cells, got ".get_debug_type($row).'.');
            }
        }
        if ($columns === [] && $given !== []) {
            $columns = [];
            foreach (array_keys($given[0]) as $key) {
                $columns[$key] = ucfirst(str_replace('_', ' ', (string) $key));
            }
        } elseif (array_is_list($columns)) {
            $columns = array_combine($columns, array_map(ucfirst(...), $columns));
        }

        $ids = array_map(strval(...), array_keys($columns));
        $this->columns = array_map(static fn (string $id, string $label): TableColumn => new TableColumn($id, $label), $ids, array_values($columns));
        $this->given = $given;
        $this->rows = array_map(static function (array $row) use ($ids): array {
            $cells = [];
            $by_place = array_is_list($row);
            foreach ($ids as $place => $id) {
                $cell = $row[$id] ?? ($by_place ? ($row[$place] ?? null) : null);
                if (! is_null($cell) && ! is_scalar($cell)) {
                    throw new ViewException("A Table's cell is text, a number or null, got ".get_debug_type($cell)." in '{$id}'.");
                }
                $cells[$id] = (string) $cell;
            }

            return $cells;
        }, $given);
        $this->fill();
    }

    /**
     * @param array<array<mixed>> $rows
     * @param array<string|int, string> $columns id => label, or the ids alone
     * @param int|null $selected the row's place; -1 none; null leaves it to the user
     */
    public static function make(array $rows = [], array $columns = [], ?int $selected = null): static
    {
        return new static($rows, $columns, $selected);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->table($name, $this->columns, $this->rows);
    }

    public function kind(): string
    {
        return 'table';
    }

    /**
     * Run $handler when the user selects, with the row as it was written (null for none), its
     * place and the mail.
     *
     * @param Closure(?array, ?int, RowSelected): mixed $handler
     */
    public function onSelect(Closure $handler): static
    {
        $given = $this->given;

        return $this->on('select', static fn (RowSelected $mail) => $handler(is_null($mail->row) ? null : ($given[$mail->row] ?? null), $mail->row, $mail));
    }

    public function settle(TKPrimitive $primitive, ?Element $before, array $children): void
    {
        if (is_null($this->selected) || ($before instanceof self && $before->selected === $this->selected && $before->rows === $this->rows)) {
            return;
        }

        /** @var TKTable $primitive */
        $row = $this->selected >= 0 && $this->selected < count($this->rows) ? $this->selected : null;
        if ($primitive->selectedRow() !== $row) {
            $primitive->selectRow($row);
        }
    }

    protected function shape(): array
    {
        return ['columns' => $this->columns];
    }

    protected function made(): array
    {
        return ['rows' => $this->rows];
    }

    protected function valueOf(string $prop): mixed
    {
        return $prop === 'rows' ? $this->rows : parent::valueOf($prop);
    }

    protected function setters(): array
    {
        return parent::setters() + [
            // New rows keep the user's selection while it is the same row.
            'rows' => function (TKTable $p, array $rows): void {
                $at = $p->selectedRow();
                $was = is_null($at) ? null : $p->rows()[$at];
                $p->setRows($rows);
                if (! is_null($was) && ($rows[$at] ?? null) === $was) {
                    $p->selectRow($at);
                }
            },
        ];
    }
}
