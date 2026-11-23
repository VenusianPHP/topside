<?php

namespace Surface\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\UI\TableColumn;
use Surface\Contracts\Toolkits\UI\TKTree as PrimitiveContract;
use Surface\Contracts\Toolkits\UI\TreeNode;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\UI\Concerns\IgnoresItsOwnWrites;
use Surface\Contracts\Toolkits\Mail\Views\NodeSelected;
use Surface\Contracts\Toolkits\Mail\Views\NodeExpanded;
use Surface\Contracts\Toolkits\Mail\Views\NodeActivated;
use Surface\NutsAndBolts\Color;

/**
 * The nodes, an id index the driver addresses them by, and the expansion record. Expansion lives
 * here because a native may forget it (GTK drops a collapsed node's rows): applyExpanded() only ever
 * reaches a node whose ancestors are all expanded, and reopen() re-applies the record below a node
 * that just opened. So does the selection, which answers the same on every toolkit. The
 * toolkit calls choseNode(), openedNode() and activatedNode() from the native's own signals;
 * the mail is made here.
 */
abstract class TKTree extends TKPrimitive implements PrimitiveContract
{
    use IgnoresItsOwnWrites;

    /**
     * @var list<TableColumn>
     */
    protected array $columns;

    private ?string $selected = null;

    /**
     * @var list<TreeNode>
     */
    protected array $nodes;

    /**
     * @var array<string, true> ids of expanded nodes, only nodes with children
     */
    protected array $expanded = [];

    /**
     * @var array<string, TreeNode>
     */
    private array $index = [];

    /**
     * @var array<string, string|null> id => parent id, null for a root
     */
    private array $parents = [];

    /**
     * @param list<TableColumn> $columns
     * @param list<TreeNode> $nodes
     * @throws ToolkitException When the name is not valid, the columns are not a non-empty list of TableColumn with unique ids, or a node id appears twice.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        array $columns = [],
        array $nodes = [],
    ) {
        $this->columns = self::guardColumns($columns);
        $this->index($nodes);
        parent::__construct($name, $window, $parent, $placement);
    }

    public function columns(): array
    {
        return $this->columns;
    }

    public function nodes(): array
    {
        return $this->nodes;
    }

    public function setNodes(array $nodes): static
    {
        $this->guardLive();
        $keep = $this->selectedNode();
        $this->index($nodes);
        $this->expanded = array_filter(
            $this->expanded,
            fn (bool $on, string $id): bool => isset($this->index[$id]) && $this->index[$id]->children !== [],
            ARRAY_FILTER_USE_BOTH,
        );
        $this->selected = ! is_null($keep) && isset($this->index[$keep]) ? $keep : null;
        $this->write(fn () => $this->reload($this->selected));

        return $this;
    }

    public function expand(string $id, bool $expanded = true): static
    {
        $this->guardLive();
        $node = $this->node($id);
        if ($expanded) {
            if ($node->children === [] || isset($this->expanded[$id])) {
                return $this;
            }
            $this->expanded[$id] = true;
            if ($this->ancestorsOpen($id)) {
                $this->write(function () use ($id): void {
                    $this->applyExpanded($id, true);
                    $this->reopen($id);
                });
            }

            return $this;
        }

        if (! isset($this->expanded[$id])) {
            return $this;
        }
        unset($this->expanded[$id]);
        $hides = $this->hidesSelection($id);
        if ($hides) {
            $this->selected = null;
        }
        if ($this->ancestorsOpen($id)) {
            $this->write(function () use ($id, $hides): void {
                $this->applyExpanded($id, false);
                if ($hides) {
                    $this->applySelected(null);
                }
            });
        }

        return $this;
    }

    public function isExpanded(string $id): bool
    {
        $this->node($id);

        return isset($this->expanded[$id]);
    }

    public function selectedNode(): ?string
    {
        $this->guardLive();

        return $this->selected;
    }

    public function selectNode(?string $id): static
    {
        $this->guardLive();
        if (is_null($id)) {
            $this->selected = null;
            $this->write(fn () => $this->applySelected(null));

            return $this;
        }

        $this->node($id);
        $this->selected = $id;
        $this->write(function () use ($id): void {
            $this->openAncestors($id);
            $this->applySelected($id);
        });

        return $this;
    }

    /**
     * The user selected a node, or nothing: record it and post NodeSelected.
     */
    protected function choseNode(?string $id): void
    {
        if (! is_null($id) && ! isset($this->index[$id])) {
            $id = null;
        }
        if ($id === $this->selected || ! $this->hearsUser()) {
            return;
        }

        $this->selected = $id;
        $this->window()->post(new NodeSelected($this->window()->name(), $this->path(), $this->uuid(), $id));
    }

    /**
     * The user opened or closed a node: record it, open again what was open below it, and
     * post NodeExpanded.
     */
    protected function openedNode(string $id, bool $expanded): void
    {
        $node = $this->index[$id] ?? null;
        if (is_null($node) || $node->children === [] || isset($this->expanded[$id]) === $expanded || ! $this->hearsUser()) {
            return;
        }

        if ($expanded) {
            $this->expanded[$id] = true;
            $this->write(fn () => $this->reopen($id));
        } else {
            unset($this->expanded[$id]);
        }
        $hides = ! $expanded && $this->hidesSelection($id);
        $this->window()->post(new NodeExpanded($this->window()->name(), $this->path(), $this->uuid(), $id, $expanded));

        if ($hides) {
            $this->selected = null;
            $this->write(fn () => $this->applySelected(null));
            $this->window()->post(new NodeSelected($this->window()->name(), $this->path(), $this->uuid(), null));
        }
    }

    /**
     * The user activated a node: a double click, or Return.
     */
    protected function activatedNode(string $id): void
    {
        if (isset($this->index[$id]) && $this->hearsUser()) {
            $this->window()->post(new NodeActivated($this->window()->name(), $this->path(), $this->uuid(), $id));
        }
    }

    /**
     * @param string $id
     * @return TreeNode
     * @throws ToolkitException When there is no such node.
     */
    protected function node(string $id): TreeNode
    {
        return $this->index[$id] ?? throw new ToolkitException("Tree '{$this->path()}' has no node '{$id}'.");
    }

    /**
     * @param string|null $id null for the roots
     * @return list<string> the children's ids in order; [] for a leaf or an unknown id
     */
    protected function childIds(?string $id): array
    {
        $children = is_null($id) ? $this->nodes : ($this->index[$id]->children ?? []);

        return array_map(fn (TreeNode $child): string => $child->id, $children);
    }

    /**
     * @param string $id
     * @return list<int> the child index at each depth, from the roots down to the node
     */
    protected function pathOf(string $id): array
    {
        $path = [];
        for ($at = $id; ! is_null($at); $at = $this->parents[$at]) {
            $path[] = (int) array_search($at, $this->childIds($this->parents[$at]), true);
        }

        return array_reverse($path);
    }

    /**
     * @param TreeNode $node
     * @return list<string> one text per column, in column order; '' for a missing cell
     */
    protected function cellTexts(TreeNode $node): array
    {
        return array_map(fn (TableColumn $column): string => $node->cells[$column->id] ?? '', $this->columns);
    }

    /**
     * @param TreeNode $node
     * @return list<Color|null> one colour per column, in column order; null for the native's own
     */
    protected function cellColors(TreeNode $node): array
    {
        return array_map(fn (TableColumn $column): ?Color => $node->colors[$column->id] ?? null, $this->columns);
    }

    /**
     * Re-apply the record below a node that just opened: each expanded child, parents first.
     *
     * @param string $id
     * @return void
     */
    protected function reopen(string $id): void
    {
        foreach ($this->childIds($id) as $child) {
            if (isset($this->expanded[$child])) {
                $this->applyExpanded($child, true);
                $this->reopen($child);
            }
        }
    }

    /**
     * Open and record every collapsed ancestor of a node, from the root down, so the node shows.
     *
     * @param string $id
     * @return void
     */
    protected function openAncestors(string $id): void
    {
        foreach (array_reverse($this->ancestors($id)) as $ancestor) {
            if (! isset($this->expanded[$ancestor])) {
                $this->expanded[$ancestor] = true;
                $this->applyExpanded($ancestor, true);
                $this->reopen($ancestor);
            }
        }
    }

    /**
     * Rebuild the native from the nodes, then re-apply the expansion record and the selection.
     * A kept selection that now sits under a collapsed node opens its ancestors, as selectNode() does.
     * The toolkit calls this last in bootstrapNative(), inside write().
     *
     * @param string|null $select
     * @return void
     */
    protected function reload(?string $select = null): void
    {
        $this->applyNodes($this->nodes);
        foreach ($this->childIds(null) as $root) {
            if (isset($this->expanded[$root])) {
                $this->applyExpanded($root, true);
                $this->reopen($root);
            }
        }
        if (! is_null($select)) {
            $this->openAncestors($select);
            $this->applySelected($select);
        }
    }

    /**
     * @param string $id
     * @return list<string> parent first, the root last
     */
    private function ancestors(string $id): array
    {
        $ancestors = [];
        for ($at = $this->parents[$id]; ! is_null($at); $at = $this->parents[$at]) {
            $ancestors[] = $at;
        }

        return $ancestors;
    }

    /**
     * A closed node hides a selection below it, so there is none: some toolkits drop it
     * themselves and some keep it out of sight.
     */
    private function hidesSelection(string $id): bool
    {
        return ! is_null($this->selected) && in_array($id, $this->ancestors($this->selected), true);
    }

    private function ancestorsOpen(string $id): bool
    {
        return array_all($this->ancestors($id), fn (string $ancestor): bool => isset($this->expanded[$ancestor]));
    }

    /**
     * Index the nodes depth first; the maps are assigned only when every id is unique.
     *
     * @param array<mixed> $nodes
     * @return void
     * @throws ToolkitException When the nodes are not a list of TreeNode or an id appears twice.
     */
    private function index(array $nodes): void
    {
        if (! array_is_list($nodes) || array_filter($nodes, fn (mixed $node): bool => ! $node instanceof TreeNode) !== []) {
            throw new ToolkitException('Tree nodes must be a list of TreeNode.');
        }

        $index = [];
        $parents = [];
        $walk = function (array $level, ?string $parent) use (&$walk, &$index, &$parents): void {
            foreach ($level as $node) {
                if (isset($index[$node->id])) {
                    throw new ToolkitException("Tree node id '{$node->id}' appears twice.");
                }
                $index[$node->id] = $node;
                $parents[$node->id] = $parent;
                $walk($node->children, $node->id);
            }
        };
        $walk($nodes, null);

        $this->nodes = $nodes;
        $this->index = $index;
        $this->parents = $parents;
    }

    /**
     * @param array<mixed> $columns
     * @return list<TableColumn>
     * @throws ToolkitException
     */
    private static function guardColumns(array $columns): array
    {
        if ($columns === [] || ! array_is_list($columns) || array_filter($columns, fn (mixed $column): bool => ! $column instanceof TableColumn) !== []) {
            throw new ToolkitException('Tree columns must be a non-empty list of TableColumn.');
        }
        $ids = array_map(fn (TableColumn $column): string => $column->id, $columns);
        if (count($ids) !== count(array_unique($ids))) {
            throw new ToolkitException('Tree column ids must be unique, got '.implode(', ', $ids).'.');
        }

        return $columns;
    }

    /**
     * Replace the native model: nothing selected, rows collapsed or as the native kept them.
     *
     * @param list<TreeNode> $nodes
     * @return void
     */
    abstract protected function applyNodes(array $nodes): void;

    /**
     * Called only while every ancestor of the node is expanded.
     *
     * @param string $id
     * @param bool $expanded
     * @return void
     */
    abstract protected function applyExpanded(string $id, bool $expanded): void;

    /**
     * Every ancestor is already expanded; scroll the node into view.
     *
     * @param string|null $id null clears the selection
     * @return void
     */
    abstract protected function applySelected(?string $id): void;
}
