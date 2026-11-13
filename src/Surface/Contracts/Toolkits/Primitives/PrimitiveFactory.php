<?php

namespace Surface\Contracts\Toolkits\Primitives;

use DateTimeImmutable;
use Surface\Contracts\Toolkits\Menus\ContextMenu;
use Surface\Contracts\Toolkits\Primitives\CollectionItem;
use Surface\Contracts\Toolkits\Primitives\ListRow;
use Surface\Contracts\Toolkits\Primitives\TableColumn;
use Surface\Contracts\Toolkits\Primitives\TKButton;
use Surface\Contracts\Toolkits\Primitives\TKCanvas;
use Surface\Contracts\Toolkits\Primitives\TKCheckbox;
use Surface\Contracts\Toolkits\Primitives\TKCollectionView;
use Surface\Contracts\Toolkits\Primitives\TKColumn;
use Surface\Contracts\Toolkits\Primitives\TKComboBox;
use Surface\Contracts\Toolkits\Primitives\TKDatepicker;
use Surface\Contracts\Toolkits\Primitives\TKDropdown;
use Surface\Contracts\Toolkits\Primitives\TKFixed;
use Surface\Contracts\Toolkits\Primitives\TKGrid;
use Surface\Contracts\Toolkits\Primitives\TKImage;
use Surface\Contracts\Toolkits\Primitives\TKLabel;
use Surface\Contracts\Toolkits\Primitives\TKLink;
use Surface\Contracts\Toolkits\Primitives\TKListView;
use Surface\Contracts\Toolkits\Primitives\TKMenuButton;
use Surface\Contracts\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Primitives\TKProgressBar;
use Surface\Contracts\Toolkits\Primitives\TKRow;
use Surface\Contracts\Toolkits\Primitives\TKScrollBar;
use Surface\Contracts\Toolkits\Primitives\TKScrollView;
use Surface\Contracts\Toolkits\Primitives\TKSegmentedControl;
use Surface\Contracts\Toolkits\Primitives\TKSeparator;
use Surface\Contracts\Toolkits\Primitives\TKSlider;
use Surface\Contracts\Toolkits\Primitives\TKSpinner;
use Surface\Contracts\Toolkits\Primitives\TKSplitView;
use Surface\Contracts\Toolkits\Primitives\TKTable;
use Surface\Contracts\Toolkits\Primitives\TKTabView;
use Surface\Contracts\Toolkits\Primitives\TKTextArea;
use Surface\Contracts\Toolkits\Primitives\TKTextInput;
use Surface\Contracts\Toolkits\Primitives\TKToggle;
use Surface\Contracts\Toolkits\Primitives\TKToggleButton;
use Surface\Contracts\Toolkits\Primitives\TKTree;
use Surface\Contracts\Toolkits\Primitives\TKVideo;
use Surface\Contracts\Toolkits\Primitives\TKWebView;
use Surface\Contracts\Toolkits\Primitives\TreeNode;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

/**
 * The driver's minter: one mint per kind, building the concrete primitive and its native.
 * Containers call it after admitting the name and placement; $host is the container the
 * primitive is created in (null = the window's content container). A kind the engine
 * lacks throws ToolkitRuntimeException("TK<Kind> is not available on <kit>.").
 */
interface PrimitiveFactory
{
    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $text
     * @return TKLabel
     */
    public function mintLabel(TKPrimitiveGroup $host, string $name, string $text): TKLabel;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $text
     * @param string $url
     * @return TKLink
     */
    public function mintLink(TKPrimitiveGroup $host, string $name, string $text, string $url): TKLink;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $label
     * @return TKButton
     */
    public function mintButton(TKPrimitiveGroup $host, string $name, string $label): TKButton;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string|null $file
     * @return TKImage
     */
    public function mintImage(TKPrimitiveGroup $host, string $name, ?string $file): TKImage;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @return TKCanvas
     */
    public function mintCanvas(TKPrimitiveGroup $host, string $name): TKCanvas;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param bool $horizontal
     * @return TKSeparator
     */
    public function mintSeparator(TKPrimitiveGroup $host, string $name, bool $horizontal): TKSeparator;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @return TKSpinner
     * @throws ToolkitRuntimeException When the engine has no spinner.
     */
    public function mintSpinner(TKPrimitiveGroup $host, string $name): TKSpinner;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param float|null $fraction
     * @return TKProgressBar
     */
    public function mintProgressBar(TKPrimitiveGroup $host, string $name, ?float $fraction): TKProgressBar;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $value
     * @param string|null $placeholder
     * @param bool $secret
     * @return TKTextInput
     */
    public function mintTextInput(TKPrimitiveGroup $host, string $name, string $value, ?string $placeholder, bool $secret): TKTextInput;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $value
     * @return TKTextArea
     */
    public function mintTextArea(TKPrimitiveGroup $host, string $name, string $value): TKTextArea;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $label
     * @param bool $checked
     * @return TKCheckbox
     */
    public function mintCheckbox(TKPrimitiveGroup $host, string $name, string $label, bool $checked): TKCheckbox;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param bool $on
     * @return TKToggle
     * @throws ToolkitRuntimeException When the engine has no switch.
     */
    public function mintToggle(TKPrimitiveGroup $host, string $name, bool $on): TKToggle;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $label
     * @param bool $pressed
     * @return TKToggleButton
     */
    public function mintToggleButton(TKPrimitiveGroup $host, string $name, string $label, bool $pressed): TKToggleButton;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param float $min
     * @param float $max
     * @param float $value
     * @return TKSlider
     */
    public function mintSlider(TKPrimitiveGroup $host, string $name, float $min, float $max, float $value): TKSlider;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param bool $vertical
     * @return TKScrollBar
     */
    public function mintScrollBar(TKPrimitiveGroup $host, string $name, bool $vertical): TKScrollBar;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param list<string> $options
     * @param int $selected
     * @return TKDropdown
     */
    public function mintDropdown(TKPrimitiveGroup $host, string $name, array $options, int $selected): TKDropdown;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $value
     * @param list<string> $options
     * @return TKComboBox
     */
    public function mintComboBox(TKPrimitiveGroup $host, string $name, string $value, array $options): TKComboBox;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $label
     * @param ContextMenu $menu parsed already
     * @return TKMenuButton
     */
    public function mintMenuButton(TKPrimitiveGroup $host, string $name, string $label, ContextMenu $menu): TKMenuButton;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param DateTimeImmutable|null $date
     * @return TKDatepicker
     */
    public function mintDatepicker(TKPrimitiveGroup $host, string $name, ?DateTimeImmutable $date): TKDatepicker;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param list<TableColumn> $columns
     * @param list<array<string, scalar|null>> $rows
     * @return TKTable
     */
    public function mintTable(TKPrimitiveGroup $host, string $name, array $columns, array $rows): TKTable;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param list<string> $segments
     * @param int $selected ignored when there are no segments
     * @return TKSegmentedControl
     */
    public function mintSegmentedControl(TKPrimitiveGroup $host, string $name, array $segments, int $selected): TKSegmentedControl;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param list<CollectionItem> $items
     * @return TKCollectionView
     */
    public function mintCollectionView(TKPrimitiveGroup $host, string $name, array $items): TKCollectionView;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param list<ListRow> $items
     * @return TKListView
     */
    public function mintListView(TKPrimitiveGroup $host, string $name, array $items): TKListView;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param list<TableColumn> $columns
     * @param list<TreeNode> $nodes
     * @return TKTree
     */
    public function mintTree(TKPrimitiveGroup $host, string $name, array $columns, array $nodes): TKTree;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string|null $file
     * @return TKVideo
     * @throws ToolkitRuntimeException When the engine has no media runtime.
     */
    public function mintVideo(TKPrimitiveGroup $host, string $name, ?string $file): TKVideo;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @return TKWebView
     * @throws ToolkitRuntimeException When the engine has no web engine.
     */
    public function mintWebView(TKPrimitiveGroup $host, string $name): TKWebView;

    /**
     * @param TKPrimitiveGroup|null $host null = the window's content container
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKColumn
     */
    public function mintColumn(?TKPrimitiveGroup $host, string $name, int $spacing, int $padding): TKColumn;

    /**
     * @param TKPrimitiveGroup|null $host null = the window's content container
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKRow
     */
    public function mintRow(?TKPrimitiveGroup $host, string $name, int $spacing, int $padding): TKRow;

    /**
     * @param TKPrimitiveGroup|null $host null = the window's content container
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKGrid
     */
    public function mintGrid(?TKPrimitiveGroup $host, string $name, int $spacing, int $padding): TKGrid;

    /**
     * @param TKPrimitiveGroup|null $host null = the window's content container
     * @param string $name
     * @return TKFixed
     */
    public function mintFixed(?TKPrimitiveGroup $host, string $name): TKFixed;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @return TKScrollView
     */
    public function mintScrollView(TKPrimitiveGroup $host, string $name): TKScrollView;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param bool $horizontal
     * @return TKSplitView
     */
    public function mintSplit(TKPrimitiveGroup $host, string $name, bool $horizontal): TKSplitView;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @return TKTabView
     */
    public function mintTabs(TKPrimitiveGroup $host, string $name): TKTabView;
}
