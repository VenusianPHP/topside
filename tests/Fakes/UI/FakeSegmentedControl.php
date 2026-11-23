<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKSegmentedControl;

class FakeSegmentedControl extends TKSegmentedControl
{
    use FakeNative;

    /** What the user does to the native. */
    public function nativeChosen(int $index): void
    {
        $this->chosen($index);
    }

    protected function applySegments(array $segments, int $selected): void
    {
        $this->record('applySegments', $segments, $selected);
        $this->chosen(0);
    }

    protected function applySegment(int $index, string $label): void
    {
        $this->record('applySegment', $index, $label);
    }

    protected function applySelected(int $index): void
    {
        $this->record('applySelected', $index);
        $this->chosen(0);
    }
}
