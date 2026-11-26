<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKButton;

class FakeButton extends TKButton
{
    use FakeNative;

    /**
     * The user clicks the native button: the toolkit's click signal calls clicked().
     */
    public function click(): void
    {
        $this->clicked();
    }

    protected function applyLabel(string $label): void
    {
        $this->record('applyLabel', $label);
    }

    protected function applyStyle(\Surface\Contracts\Toolkits\UI\ButtonStyle $style): void
    {
        $this->record('applyStyle', $style);
    }

    protected function applyFont(\Surface\Contracts\Toolkits\Styling\FontSpec $font): void
    {
        $this->record('applyFont', $font);
    }

    protected function applyTextColor(?\Surface\NutsAndBolts\Color $color): void
    {
        $this->record('applyTextColor', $color);
    }

    protected function applyAlignment(\Surface\Contracts\Toolkits\Styling\TextAlignment $alignment): void
    {
        $this->record('applyAlignment', $alignment);
    }

    protected function applyIcon(?string $file, ?string $symbol): void
    {
        $this->record('applyIcon', $file, $symbol);
    }

    protected function applyIconPosition(\Surface\Contracts\Toolkits\UI\IconPosition $position): void
    {
        $this->record('applyIconPosition', $position);
    }

    protected function applyRole(?\Surface\Contracts\Toolkits\UI\ButtonRole $role): void
    {
        $this->record('applyRole', $role);
    }

    protected function applyPadding(\Surface\Contracts\Toolkits\UI\Insets $padding): void
    {
        $this->record('applyPadding', $padding);
    }
}
