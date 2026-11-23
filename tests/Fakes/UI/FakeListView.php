<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKListView;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;

class FakeListView extends TKListView
{
    use FakeNative;

    protected function applyItems(array $items): void
    {
        $this->record('applyItems', $items);
    }

    protected function applyInserted(int $at, array $items): void
    {
        $this->record('applyInserted', $at, $items);
    }

    protected function applyRemoved(int $at, int $count): void
    {
        $this->record('applyRemoved', $at, $count);
    }

    protected function applyUpdated(int $index, object $item): void
    {
        $this->record('applyUpdated', $index, $item);
    }

    protected function applyScrollTo(float $x, float $y): void
    {
        $this->record('applyScrollTo', $x, $y);
    }

    protected function nativeScrollPosition(): ScrollPosition
    {
        return new ScrollPosition(0.0, 0.0, 1.0, 1.0);
    }

    /** What the user does to the native. */
    public function nativeActivated(int $index): void
    {
        $this->activated($index);
    }

    /** What the user does to the native. */
    public function nativeAction(int $index): void
    {
        $this->actionClicked($index);
    }

    protected function applyRowHeight(int $height): void
    {
        $this->record('applyRowHeight', $height);
    }
}
