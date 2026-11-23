<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\TKTree;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TreeNode;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TableColumn;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\NodeExpanded;
use Surface\Contracts\Toolkits\Mail\Views\NodeSelected;
use Surface\Contracts\Toolkits\Mail\Views\NodeActivated;

/**
 * Nodes inside nodes. The short way is nested arrays, and then a node's id is its path:
 * ['src' => ['Kernel.php'], 'README.md'] has src, src/Kernel.php and README.md. The long
 * way is a list of arrays: id, label, children, color, and a cell for each further column.
 *
 *     <Tree :nodes="$files" @activate="fn ($id) => $this->open($id)" />
 */
class Tree extends Element
{
    /** @var list<TableColumn> */
    protected readonly array $columns;

    /** @var list<TreeNode> */
    protected readonly array $nodes;

    final public function __construct(
        array $nodes = [],
        array $columns = [],
        protected readonly ?string $selected = null,
        protected readonly ?array $expanded = null,
    ) {
        $columns = match (true) {
            $columns === [] => ['label' => ''],
            array_is_list($columns) => array_combine($columns, array_map(ucfirst(...), $columns)),
            default => $columns,
        };
        $ids = array_map(strval(...), array_keys($columns));
        $this->columns = array_map(static fn (string $id, string $label): TableColumn => new TableColumn($id, $label), $ids, array_values($columns));
        $this->nodes = self::read($nodes, $ids, '');
        $this->fill();
    }

    /**
     * @param array<mixed> $nodes
     * @param array<string|int, string> $columns id => label, or the ids alone; the first holds the expander
     * @param string|null $selected a node's id; '' none; null leaves it to the user
     * @param list<string>|null $expanded every open node's id; null leaves it to the user
     */
    public static function make(array $nodes = [], array $columns = [], ?string $selected = null, ?array $expanded = null): static
    {
        return new static($nodes, $columns, $selected, $expanded);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->tree($name, $this->columns, $this->nodes);
    }

    public function kind(): string
    {
        return 'tree';
    }

    /**
     * Run $handler when the user selects, with the node's id (null for none) and the mail.
     *
     * @param Closure(?string, NodeSelected): mixed $handler
     */
    public function onSelect(Closure $handler): static
    {
        return $this->on('select', static fn (NodeSelected $mail) => $handler($mail->id, $mail));
    }

    /**
     * Run $handler when the user opens or closes a node, with its id, whether it is open and the mail.
     *
     * @param Closure(string, bool, NodeExpanded): mixed $handler
     */
    public function onExpand(Closure $handler): static
    {
        return $this->on('expand', static fn (NodeExpanded $mail) => $handler($mail->id, $mail->expanded, $mail));
    }

    /**
     * Run $handler when the user activates a node (a double click, or Return), with its id and the mail.
     *
     * @param Closure(string, NodeActivated): mixed $handler
     */
    public function onActivate(Closure $handler): static
    {
        return $this->on('activate', static fn (NodeActivated $mail) => $handler($mail->id, $mail));
    }

    public function settle(TKPrimitive $primitive, ?Element $before, array $children): void
    {
        $same = $before instanceof self && self::same($before->nodes, $this->nodes);
        [$holds, $opens] = self::ids($this->nodes);

        /** @var TKTree $primitive */
        if (! is_null($this->expanded) && (! $same || $before->expanded !== $this->expanded)) {
            foreach ($opens as $id) {
                $open = in_array($id, $this->expanded, true);
                if ($primitive->isExpanded($id) !== $open) {
                    $primitive->expand($id, $open);
                }
            }
        }
        if (! is_null($this->selected) && (! $same || $before->selected !== $this->selected)) {
            $id = in_array($this->selected, $holds, true) ? $this->selected : null;
            if ($primitive->selectedNode() !== $id) {
                $primitive->selectNode($id);
            }
        }
    }

    protected function shape(): array
    {
        return ['columns' => $this->columns];
    }

    protected function made(): array
    {
        return ['nodes' => $this->nodes];
    }

    protected function valueOf(string $prop): mixed
    {
        return $prop === 'nodes' ? $this->nodes : parent::valueOf($prop);
    }

    protected function setters(): array
    {
        return parent::setters() + ['nodes' => fn (TKTree $p, array $v) => $p->setNodes($v)];
    }

    /**
     * @param list<string> $columns
     * @return list<TreeNode>
     * @throws ViewException
     */
    private static function read(array $given, array $columns, string $under): array
    {
        $nodes = [];
        foreach ($given as $key => $value) {
            if (is_string($key) && is_array($value)) {
                $nodes[] = new TreeNode($under.$key, [$columns[0] => $key], self::read($value, $columns, "{$under}{$key}/"));
                continue;
            }
            if (is_scalar($value)) {
                $nodes[] = new TreeNode($under.$value, [$columns[0] => (string) $value]);
                continue;
            }
            if (! is_array($value) || (! isset($value['id']) && ! isset($value['label']))) {
                throw new ViewException('A Tree node is text, label => [children], or an array with an id or a label, got '.get_debug_type($value)." under '{$under}'.");
            }

            $id = (string) ($value['id'] ?? $under.$value['label']);
            $cells = [];
            foreach ($columns as $place => $column) {
                $cells[$column] = (string) ($value[$column] ?? ($place === 0 ? ($value['label'] ?? $id) : ''));
            }
            $color = $value['color'] ?? null;
            $nodes[] = new TreeNode(
                $id,
                $cells,
                self::read($value['children'] ?? [], $columns, "{$id}/"),
                is_null($color) ? [] : [$columns[0] => $color instanceof Color ? $color : Color::hex($color)],
            );
        }

        return $nodes;
    }

    /**
     * @param list<TreeNode> $nodes
     * @return array{list<string>, list<string>} every id, and those with children
     */
    private static function ids(array $nodes): array
    {
        [$holds, $opens] = [[], []];
        foreach ($nodes as $node) {
            $holds[] = $node->id;
            if ($node->children !== []) {
                $opens[] = $node->id;
                [$inner, $inner_opens] = self::ids($node->children);
                array_push($holds, ...$inner);
                array_push($opens, ...$inner_opens);
            }
        }

        return [$holds, $opens];
    }
}
