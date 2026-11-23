<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKSlider;

class FakeSlider extends TKSlider
{
    use FakeNative;

    /** What the user does to the native. */
    public function nativeSlid(float $value): void
    {
        $this->slid($value);
    }

    protected function applyValue(float $value): void
    {
        $this->record('applyValue', $value);
        $this->slid(round($value, 1));
    }

    protected function applyRange(float $min, float $max, float $value): void
    {
        $this->record('applyRange', $min, $max, $value);
        $this->slid($min);
    }
}
