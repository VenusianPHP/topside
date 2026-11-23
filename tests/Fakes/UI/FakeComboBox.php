<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKComboBox;

class FakeComboBox extends TKComboBox
{
    use FakeNative;

    public bool $focused = false;

    public function hasFocus(): bool
    {
        return $this->focused;
    }

    public function nativeTyped(string $text): void
    {
        $this->typed($text);
    }

    public function nativeSubmitted(string $text): void
    {
        $this->submitted($text);
    }

    public function nativeChoseOption(int $index): void
    {
        $this->choseOption($index);
    }

    public function nativeChoseCompletion(int $row): void
    {
        $this->choseCompletion($this->completions()[$row]);
    }

    public function nativeHid(): void
    {
        $this->completionsHidden();
    }

    protected function applyValue(string $value): void
    {
        $this->record("applyValue", $value);
        $this->typed("");
    }

    protected function applyOptions(array $options): void
    {
        $this->record("applyOptions", $options);
    }

    protected function applyCompletions(array $completions): void
    {
        $this->record("applyCompletions", $completions);
    }
}
