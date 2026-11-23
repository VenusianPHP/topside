<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKTextInput;

class FakeTextInput extends TKTextInput
{
    use FakeNative;

    public function nativeTyped(string $text): void
    {
        $this->typed($text);
    }

    public function nativeSubmitted(string $text): void
    {
        $this->submitted($text);
    }

    protected function applyValue(string $value): void
    {
        $this->record("applyValue", $value);
        $this->typed("");    // a toolkit signals its own writes, some in steps
        $this->typed($value);
    }

    protected function applyPlaceholder(?string $placeholder): void
    {
        $this->record("applyPlaceholder", $placeholder);
    }

    protected function applyFont(\Surface\Contracts\Toolkits\Styling\FontSpec $font): void
    {
        $this->record("applyFont", $font);
    }

    protected function applyTextColor(?\Surface\NutsAndBolts\Color $color): void
    {
        $this->record("applyTextColor", $color);
    }

    protected function applyAlignment(\Surface\Contracts\Toolkits\Styling\TextAlignment $alignment): void
    {
        $this->record("applyAlignment", $alignment);
    }
}
