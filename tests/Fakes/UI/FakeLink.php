<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKLink;

class FakeLink extends TKLink
{
    use FakeNative;

    protected function applyText(string $text): void
    {
        $this->record("applyText", $text);
    }

    protected function applyUrl(string $url): void
    {
        $this->record("applyUrl", $url);
    }

    protected function applyFont(\Surface\Contracts\Toolkits\Styling\FontSpec $font): void
    {
        $this->record("applyFont", $font);
    }

    protected function applyAlignment(\Surface\Contracts\Toolkits\Styling\TextAlignment $alignment): void
    {
        $this->record("applyAlignment", $alignment);
    }

    protected function applyTextColor(?\Surface\NutsAndBolts\Color $color): void
    {
        $this->record("applyTextColor", $color);
    }
}
