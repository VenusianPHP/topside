<?php

namespace Venusian\Topside\Tests\Fakes;

use Surface\Toolkits\Drivers\ToolkitAdapterDriver;
use Surface\Contracts\Toolkits\UI\TKRow as RowContract;
use Surface\Contracts\Toolkits\UI\TKLabel as LabelContract;
use Surface\Contracts\Toolkits\UI\TKButton as ButtonContract;
use Surface\Contracts\Toolkits\UI\TKColumn as ColumnContract;
use Venusian\Topside\Tests\Fakes\UI\FakeRow;
use Venusian\Topside\Tests\Fakes\UI\FakeLabel;
use Venusian\Topside\Tests\Fakes\UI\FakeButton;
use Venusian\Topside\Tests\Fakes\UI\FakeColumn;
use Venusian\Topside\Tests\Fakes\UI\FakeToggle;
use Venusian\Topside\Tests\Fakes\UI\FakeCheckbox;
use Venusian\Topside\Tests\Fakes\UI\FakeDropdown;
use Surface\Contracts\Toolkits\UI\TKToggle as ToggleContract;
use Surface\Contracts\Toolkits\UI\TKCheckbox as CheckboxContract;
use Surface\Contracts\Toolkits\UI\TKDropdown as DropdownContract;
use Venusian\Topside\Tests\Fakes\UI\FakeLink;
use Surface\Contracts\Toolkits\UI\TKLink as LinkContract;
use Venusian\Topside\Tests\Fakes\UI\FakeTextInput;
use Surface\Contracts\Toolkits\UI\TKTextInput as TextInputContract;
use Venusian\Topside\Tests\Fakes\UI\FakeComboBox;
use Surface\Contracts\Toolkits\UI\TKComboBox as ComboBoxContract;
use Venusian\Topside\Tests\Fakes\UI\FakeDatepicker;
use Surface\Contracts\Toolkits\UI\TKDatepicker as DatepickerContract;
use Venusian\Topside\Tests\Fakes\UI\FakeSeparator;
use Surface\Contracts\Toolkits\UI\TKSeparator as SeparatorContract;
use Venusian\Topside\Tests\Fakes\UI\FakeProgressBar;
use Surface\Contracts\Toolkits\UI\TKProgressBar as ProgressBarContract;
use Venusian\Topside\Tests\Fakes\UI\FakeSpinner;
use Surface\Contracts\Toolkits\UI\TKSpinner as SpinnerContract;
use Venusian\Topside\Tests\Fakes\UI\FakeSlider;
use Surface\Contracts\Toolkits\UI\TKSlider as SliderContract;
use Venusian\Topside\Tests\Fakes\UI\FakeToggleButton;
use Surface\Contracts\Toolkits\UI\TKToggleButton as ToggleButtonContract;
use Venusian\Topside\Tests\Fakes\UI\FakeSegmentedControl;
use Surface\Contracts\Toolkits\UI\TKSegmentedControl as SegmentedControlContract;
use Venusian\Topside\Tests\Fakes\UI\FakeImage;
use Surface\Contracts\Toolkits\UI\TKImage as ImageContract;
use Venusian\Topside\Tests\Fakes\UI\FakeScrollView;
use Surface\Contracts\Toolkits\UI\TKScrollView as ScrollViewContract;
use Venusian\Topside\Tests\Fakes\UI\FakeTabView;
use Surface\Contracts\Toolkits\UI\TKTabView as TabViewContract;
use Venusian\Topside\Tests\Fakes\UI\FakeSplitView;
use Surface\Contracts\Toolkits\UI\TKSplitView as SplitViewContract;
use Venusian\Topside\Tests\Fakes\UI\FakeGrid;
use Surface\Contracts\Toolkits\UI\TKGrid as GridContract;
use Venusian\Topside\Tests\Fakes\UI\FakeTextArea;
use Surface\Contracts\Toolkits\UI\TKTextArea as TextAreaContract;
use Venusian\Topside\Tests\Fakes\UI\FakeListView;
use Surface\Contracts\Toolkits\UI\TKListView as ListViewContract;
use Venusian\Topside\Tests\Fakes\UI\FakeCollectionView;
use Surface\Contracts\Toolkits\UI\TKCollectionView as CollectionViewContract;
use Venusian\Topside\Tests\Fakes\UI\FakeTable;
use Surface\Contracts\Toolkits\UI\TKTable as TableContract;
use Venusian\Topside\Tests\Fakes\UI\FakeTree;
use Surface\Contracts\Toolkits\UI\TKTree as TreeContract;
use Surface\Contracts\Toolkits\Menus\TKMenuBar;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Venusian\Topside\Tests\Fakes\Menus\FakeMenuBar;
use Voyager\Contracts\Core\FrameworkCore;

class FakeDriver extends ToolkitAdapterDriver
{
    /** Drivers built in this process. */
    public static int $built = 0;

    protected string $window = FakeWindow::class;

    protected ?string $menu_bar = FakeMenuBar::class;

    /** @var array<string, FakeMenuBar> by window, '' for when none is focused */
    public array $bars = [];

    protected array $primitives = [
        ColumnContract::class => FakeColumn::class,
        RowContract::class => FakeRow::class,
        LabelContract::class => FakeLabel::class,
        ButtonContract::class => FakeButton::class,
        CheckboxContract::class => FakeCheckbox::class,
        ToggleContract::class => FakeToggle::class,
        DropdownContract::class => FakeDropdown::class,
        LinkContract::class => FakeLink::class,
        TextInputContract::class => FakeTextInput::class,
        ComboBoxContract::class => FakeComboBox::class,
        DatepickerContract::class => FakeDatepicker::class,
        SeparatorContract::class => FakeSeparator::class,
        ProgressBarContract::class => FakeProgressBar::class,
        SpinnerContract::class => FakeSpinner::class,
        SliderContract::class => FakeSlider::class,
        ToggleButtonContract::class => FakeToggleButton::class,
        SegmentedControlContract::class => FakeSegmentedControl::class,
        ImageContract::class => FakeImage::class,
        ScrollViewContract::class => FakeScrollView::class,
        TabViewContract::class => FakeTabView::class,
        SplitViewContract::class => FakeSplitView::class,
        GridContract::class => FakeGrid::class,
        TextAreaContract::class => FakeTextArea::class,
        ListViewContract::class => FakeListView::class,
        CollectionViewContract::class => FakeCollectionView::class,
        TableContract::class => FakeTable::class,
        TreeContract::class => FakeTree::class,
    ];

    /** @var list<bool> whether the session was connected each time connected() ran */
    public array $connected_hook = [];

    public function __construct(FrameworkCore $app)
    {
        static::$built++;
        parent::__construct($app);
    }

    protected function instanceSession(): void
    {
        $this->session ??= new FakeSession($this->app);
    }

    protected function connected(): void
    {
        $this->connected_hook[] = $this->session->connected();
    }

    public function menuBar(?ToolkitPoweredWindow $window = null): ?TKMenuBar
    {
        $bar = parent::menuBar($window);
        if (! is_null($bar)) {
            $this->bars[$window?->name() ?? ''] = $bar;
        }

        return $bar;
    }

    /** A toolkit whose bar is not ported yet. */
    public function withoutMenuBar(): void
    {
        $this->menu_bar = null;
    }

    /** @return array<string, FakeWindow> */
    public function windows(): array
    {
        return $this->windows;
    }
}
