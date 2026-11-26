<?php

namespace Surface\Toolkits\UI;

use Closure;
use DateTimeImmutable;
use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\Mail\Views\GroupClicked;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\UI\TKRow as RowContract;
use Surface\Contracts\Toolkits\UI\TKFixed as FixedContract;
use Surface\Contracts\Toolkits\UI\TKOverlay as OverlayContract;
use Surface\Contracts\Toolkits\UI\TKLabel as LabelContract;
use Surface\Contracts\Toolkits\UI\TKButton as ButtonContract;
use Surface\Contracts\Toolkits\UI\TKColumn as ColumnContract;
use Surface\Contracts\Toolkits\UI\TKToggle as ToggleContract;
use Surface\Contracts\Toolkits\UI\TKCheckbox as CheckboxContract;
use Surface\Contracts\Toolkits\UI\TKDropdown as DropdownContract;
use Surface\Contracts\Toolkits\UI\TKLink as LinkContract;
use Surface\Contracts\Toolkits\UI\TKComboBox as ComboBoxContract;
use Surface\Contracts\Toolkits\UI\TKTextInput as TextInputContract;
use Surface\Contracts\Toolkits\UI\TKDatepicker as DatepickerContract;
use Surface\Contracts\Toolkits\UI\TKSeparator as SeparatorContract;
use Surface\Contracts\Toolkits\UI\TKProgressBar as ProgressBarContract;
use Surface\Contracts\Toolkits\UI\TKSpinner as SpinnerContract;
use Surface\Contracts\Toolkits\UI\TKSlider as SliderContract;
use Surface\Contracts\Toolkits\UI\TKToggleButton as ToggleButtonContract;
use Surface\Contracts\Toolkits\UI\TKSegmentedControl as SegmentedControlContract;
use Surface\Contracts\Toolkits\UI\TKImage as ImageContract;
use Surface\Contracts\Toolkits\UI\TKWebView as WebViewContract;
use Surface\Contracts\Toolkits\UI\TKGrid as GridContract;
use Surface\Contracts\Toolkits\UI\TKScrollView as ScrollViewContract;
use Surface\Contracts\Toolkits\UI\TKSplitView as SplitViewContract;
use Surface\Contracts\Toolkits\UI\TKTabView as TabViewContract;
use Surface\Contracts\Toolkits\UI\TKTextArea as TextAreaContract;
use Surface\Contracts\Toolkits\UI\TKListView as ListViewContract;
use Surface\Contracts\Toolkits\UI\TKCollectionView as CollectionViewContract;
use Surface\Contracts\Toolkits\UI\TKTable as TableContract;
use Surface\Contracts\Toolkits\UI\TKTree as TreeContract;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup as PrimitiveContract;

/**
 * What every toolkit's container does the same way: it creates its children, holds them by
 * name and in order, answers lookups by path, and removes them before itself.
 * The toolkit's side is two hooks: put a child's native inside this one, and take it out.
 * Which class is each kind of primitive comes from the toolkit driver's primitive map.
 *
 * The tree is the only record: a path is walked through it, so there is nothing else to
 * keep in step when a primitive is added, moved or removed.
 */
abstract class TKPrimitiveGroup extends TKPrimitive implements PrimitiveContract
{
    /**
     * The children by name, in order: creation order, and for a column or row the order
     * they are displayed in.
     *
     * @var array<string, TKPrimitive>
     */
    protected array $children = [];

    /**
     * What expands() last answered for each axis, as the container above was told.
     *
     * @var array{bool, bool}
     */
    private array $expanding = [false, false];

    private bool $clickable = false;

    public function label(string $name, string $text): LabelContract
    {
        return $this->create(LabelContract::class, $name, $text);
    }

    public function button(string $name, string $label): ButtonContract
    {
        return $this->create(ButtonContract::class, $name, $label);
    }

    public function checkbox(string $name, string $label, bool $checked = false): CheckboxContract
    {
        return $this->create(CheckboxContract::class, $name, $label, $checked);
    }

    public function toggle(string $name, bool $on = false): ToggleContract
    {
        return $this->create(ToggleContract::class, $name, $on);
    }

    /**
     * @param list<string> $options
     */
    public function dropdown(string $name, array $options, int $selected = 0): DropdownContract
    {
        return $this->create(DropdownContract::class, $name, $options, $selected);
    }

    public function link(string $name, string $text, string $url): LinkContract
    {
        return $this->create(LinkContract::class, $name, $text, $url);
    }

    public function textInput(string $name, string $value = '', ?string $placeholder = null, bool $secret = false, bool $search = false): TextInputContract
    {
        return $this->create(TextInputContract::class, $name, $value, $placeholder, $secret, $search);
    }

    /**
     * @param list<string> $options
     */
    public function comboBox(string $name, string $value = '', array $options = []): ComboBoxContract
    {
        return $this->create(ComboBoxContract::class, $name, $value, $options);
    }

    public function datepicker(string $name, ?DateTimeImmutable $date = null): DatepickerContract
    {
        return $this->create(DatepickerContract::class, $name, $date);
    }

    public function separator(string $name, bool $horizontal = true): SeparatorContract
    {
        return $this->create(SeparatorContract::class, $name, $horizontal);
    }

    public function progressBar(string $name, ?float $fraction = 0.0): ProgressBarContract
    {
        return $this->create(ProgressBarContract::class, $name, $fraction);
    }

    public function spinner(string $name, bool $spinning = true): SpinnerContract
    {
        return $this->create(SpinnerContract::class, $name, $spinning);
    }

    public function slider(string $name, float $min, float $max, float $value): SliderContract
    {
        return $this->create(SliderContract::class, $name, $min, $max, $value);
    }

    public function toggleButton(string $name, string $label, bool $pressed = false): ToggleButtonContract
    {
        return $this->create(ToggleButtonContract::class, $name, $label, $pressed);
    }

    /**
     * @param list<string> $segments
     */
    public function segmentedControl(string $name, array $segments, int $selected = 0): SegmentedControlContract
    {
        return $this->create(SegmentedControlContract::class, $name, $segments, $selected);
    }

    public function image(string $name, ?string $file = null): ImageContract
    {
        return $this->create(ImageContract::class, $name, $file);
    }

    public function webView(string $name): WebViewContract
    {
        return $this->create(WebViewContract::class, $name);
    }

    public function column(string $name, int $spacing = 0, Insets|int $padding = 0): ColumnContract
    {
        return $this->create(ColumnContract::class, $name, $spacing, $padding);
    }

    public function row(string $name, int $spacing = 0, Insets|int $padding = 0): RowContract
    {
        return $this->create(RowContract::class, $name, $spacing, $padding);
    }

    public function textArea(string $name, string $value = ''): TextAreaContract
    {
        return $this->create(TextAreaContract::class, $name, $value);
    }

    /**
     * @param list<\Surface\Contracts\Toolkits\UI\ListRow> $items
     */
    public function listView(string $name, array $items = []): ListViewContract
    {
        return $this->create(ListViewContract::class, $name, $items);
    }

    /**
     * @param list<\Surface\Contracts\Toolkits\UI\CollectionItem> $items
     */
    public function collectionView(string $name, array $items = []): CollectionViewContract
    {
        return $this->create(CollectionViewContract::class, $name, $items);
    }

    /**
     * @param list<\Surface\Contracts\Toolkits\UI\TableColumn> $columns
     * @param list<array<string, scalar|null>> $rows
     */
    public function table(string $name, array $columns, array $rows = []): TableContract
    {
        return $this->create(TableContract::class, $name, $columns, $rows);
    }

    /**
     * @param list<\Surface\Contracts\Toolkits\UI\TableColumn> $columns
     * @param list<\Surface\Contracts\Toolkits\UI\TreeNode> $nodes
     */
    public function tree(string $name, array $columns, array $nodes = []): TreeContract
    {
        return $this->create(TreeContract::class, $name, $columns, $nodes);
    }

    public function grid(string $name, int $spacing = 0, Insets|int $padding = 0): GridContract
    {
        return $this->create(GridContract::class, $name, $spacing, $padding);
    }

    public function scrollView(string $name): ScrollViewContract
    {
        return $this->create(ScrollViewContract::class, $name);
    }

    public function split(string $name, bool $horizontal = true): SplitViewContract
    {
        return $this->create(SplitViewContract::class, $name, $horizontal);
    }

    public function tabs(string $name): TabViewContract
    {
        return $this->create(TabViewContract::class, $name);
    }

    public function fixed(string $name): FixedContract
    {
        return $this->create(FixedContract::class, $name);
    }

    public function overlay(string $name, Insets|int $padding = 0): OverlayContract
    {
        return $this->create(OverlayContract::class, $name, $padding);
    }

    /**
     * @return list<TKPrimitive>
     */
    public function children(): array
    {
        return array_values($this->children);
    }

    /**
     * One name is a child; each dot goes one container deeper. Null when a name on the way
     * is not there, or is not a container.
     */
    public function view(string $path): ?TKPrimitive
    {
        $at = $this;

        foreach (explode('.', $path) as $name) {
            if (! $at instanceof self) {
                return null;
            }

            $at = $at->children[$name] ?? null;
        }

        return $at;
    }

    /**
     * Make a child of the kind $contract names. Every refusal comes before a native exists:
     * the name first, then the kind, then where the child goes.
     *
     * @param class-string $contract the primitive's contract, e.g. TKButton
     * @param mixed ...$with what that kind is built with, after its name, window, parent and placement
     * @throws ToolkitException For a refused name, or a kind this toolkit does not have.
     */
    protected function create(string $contract, string $name, mixed ...$with): TKPrimitive
    {
        $this->admit($name);

        $class = $this->concreteFor($contract);
        if (is_null($class)) {
            $kind = substr(strrchr($contract, '\\'), 1);
            throw new ToolkitException("This toolkit has no {$kind}: '{$name}' cannot be made in '{$this->path()}'.");
        }

        return new $class($name, $this->window(), $this, $this->nextPlacement(), ...$with);
    }

    /**
     * Where the next child goes. Next in line, unless the container places its children
     * some other way.
     */
    protected function nextPlacement(): Placement
    {
        return Placement::next();
    }

    /**
     * Accept $name for a child about to be built, before its native exists.
     *
     * @throws ToolkitException When this container is removed, the name is empty or holds a
     *         dot, or a child already has it.
     */
    protected function admit(string $name): void
    {
        $this->guardLive();
        self::guardName($name);

        if (isset($this->children[$name])) {
            throw new ToolkitException("'{$this->path()}' already has a child named '{$name}'.");
        }
    }

    /**
     * Take in a child whose native now exists: last in the order, its native inside this one.
     */
    protected function adopt(TKPrimitive $child): void
    {
        $this->children[$child->name()] = $child;
        $this->attachNative($child);
    }

    /**
     * Let a child go: its name is free again and its native is out of this one.
     */
    protected function release(TKPrimitive $child): void
    {
        unset($this->children[$child->name()]);
        $this->detachNative($child);
    }

    /**
     * A child's fill(), align() or minSize() changed. Runs before the child applies its own
     * part. Nothing by default: override where the toolkit keeps a child's layout on the
     * container (a Qt layout, AppKit constraints, a fixed sizing a child to its frame).
     */
    protected function childLayoutChanged(TKPrimitive $child): void {}

    /**
     * As a container, it takes spare room when anything shown inside it does.
     */
    protected function expands(bool $horizontal): bool
    {
        if (parent::expands($horizontal)) {
            return true;
        }
        foreach ($this->children as $child) {
            if (! $child->isRemoved() && $child->isVisible() && $child->expands($horizontal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * For a toolkit whose containers keep a child's fill on their own layout: a child came,
     * went or changed, so the container above is told if this one now expands differently.
     */
    protected function tellExpansion(): void
    {
        if ($this->isRemoved()) {
            return;
        }
        $now = [$this->expands(true), $this->expands(false)];
        if ($now !== $this->expanding) {
            $this->expanding = $now;
            $this->parent()?->childLayoutChanged($this);
        }
    }

    /**
     * Whether its children have an order to be moved in.
     */
    protected function keepsOrder(): bool
    {
        return false;
    }

    /**
     * A child was shown or hidden. Runs after the child applied it. Nothing by default:
     * override where the container's own arrangement depends on which children are shown.
     */
    protected function childVisibilityChanged(TKPrimitive $child): void {}

    /**
     * The last child first, each while this native still holds it.
     */
    protected function removeChildren(): void
    {
        foreach (array_reverse($this->children) as $child) {
            $child->remove();
        }
    }

    /**
     * The toolkit's class for a kind of primitive, by its contract; null when it has none.
     * Read from the toolkit driver's primitive map, through the window.
     *
     * @param class-string $contract
     * @return class-string<TKPrimitive>|null
     */
    protected function concreteFor(string $contract): ?string
    {
        return $this->window()->primitiveFor($contract);
    }

    /**
     * @throws ToolkitException When removed.
     */
    public function setClickable(bool $on = true): static
    {
        $this->guardLive();

        if ($on !== $this->clickable) {
            $this->clickable = $on;
            $this->applyClickable($on);
        }

        return $this;
    }

    public function isClickable(): bool
    {
        return $this->clickable;
    }

    /**
     * The toolkit saw a primary press and its release on the group or on passive content in
     * it, at $x, $y from the group's top-left: post GroupClicked. Nothing while it is not
     * clickable, while it or a group above it is disabled, or once it is going.
     */
    protected function groupClicked(float $x, float $y): void
    {
        if ($this->isRemoved() || ! $this->clickable || ! $this->takesInput()) {
            return;
        }

        $this->window()->post(new GroupClicked($this->window()->name(), $this->path(), $this->uuid(), $x, $y));
    }

    /**
     * Listen for primary clicks on the native, or stop. A toolkit with no way to hear a click
     * on a plain container leaves this empty, and the group posts nothing.
     */
    protected function applyClickable(bool $on): void {}

    /**
     * Put $child's native inside this one, at the end, where $child->placement() says.
     */
    abstract protected function attachNative(TKPrimitive $child): void;

    /**
     * Take $child's native out of this one. $child destroys it right after.
     */
    abstract protected function detachNative(TKPrimitive $child): void;
}
