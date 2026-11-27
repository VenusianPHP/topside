<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\NutsAndBolts\Color;
use Surface\Toolkits\UI\TKLabel;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;

class FakeLabel extends TKLabel
{
    use FakeNative;

    protected function applyText(string $text): void
    {
        $this->record('applyText', $text);
    }

    protected function applyRuns(array $runs): void
    {
        $this->record('applyRuns', $runs);
    }

    protected function applyWrap(bool $wrap): void
    {
        $this->record('applyWrap', $wrap);
    }

    protected function applyTruncate(bool $truncate): void
    {
        $this->record('applyTruncate', $truncate);
    }

    protected function applyAlignment(TextAlignment $alignment): void
    {
        $this->record('applyAlignment', $alignment);
    }

    protected function applyFont(FontSpec $font): void
    {
        $this->record('applyFont', $font);
    }

    protected function applyTextColor(?Color $color): void
    {
        $this->record('applyTextColor', $color);
    }
}
