<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKDropdown;

class FakeDropdown extends TKDropdown
{
    use FakeNative;

    /** The user chose option $index in the native popup. */
    public function nativeChosen(int $index): void
    {
        $this->chosen($index);
    }

    protected function applyOptions(array $options, int $selected): void
    {
        $this->record('applyOptions', $options, $selected);
    }

    protected function applySelected(int $index): void
    {
        $this->record('applySelected', $index);
        $this->chosen($index);
    }

    protected function applyIcons(array $icons): void
    {
        $this->record('applyIcons', array_map(fn (int $i): ?array => $this->iconOf($i), array_keys($this->options())));
    }

    protected function applyIconOnly(bool $on): void
    {
        $this->record('applyIconOnly', $on);
    }
}
