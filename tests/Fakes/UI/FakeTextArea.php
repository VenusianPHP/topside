<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKTextArea;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;

class FakeTextArea extends TKTextArea
{
    use FakeNative;

    protected function applyValue(string $value): void
    {
        $this->record('applyValue', $value);
        $this->typed($value);
    }

    protected function applyFont(FontSpec $font): void
    {
        $this->record('applyFont', $font);
    }

    protected function applyTextColor(?Color $color): void
    {
        $this->record('applyTextColor', $color);
    }

    protected function applyReadOnly(bool $read_only): void
    {
        $this->record('applyReadOnly', $read_only);
    }

    protected function applyWrap(bool $wrap): void
    {
        $this->record('applyWrap', $wrap);
    }

    protected function applyTabWidth(int $columns): void
    {
        $this->record('applyTabWidth', $columns);
    }

    protected function applyAlignment(TextAlignment $alignment): void
    {
        $this->record('applyAlignment', $alignment);
    }

    protected function applyLineBackgrounds(array $backgrounds): void
    {
        $this->record('applyLineBackgrounds', $backgrounds);
    }

    protected function applyRangeStyles(array $styles): void
    {
        $this->record('applyRangeStyles', $styles);
    }

    protected function applySelectLines(int $first, int $last): void
    {
        $this->record('applySelectLines', $first, $last);
    }

    protected function applyScrollToLine(int $line): void
    {
        $this->record('applyScrollToLine', $line);
    }

    protected function applyScrollTo(float $x, float $y): void
    {
        $this->record('applyScrollTo', $x, $y);
    }

    protected function nativeScrollPosition(): ScrollPosition
    {
        return new ScrollPosition(0.0, 0.0, 1.0, 1.0);
    }

    protected function nativeSelectedLines(): array
    {
        return [0, 0];
    }

    /** What the user does to the native. */
    public function nativeTyped(string $value): void
    {
        $this->typed($value);
    }
}
