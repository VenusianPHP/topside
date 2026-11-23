<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKToggleButton;

class FakeToggleButton extends TKToggleButton
{
    use FakeNative;

    /** What the user does to the native. */
    public function nativeToggled(bool $pressed): void
    {
        $this->toggled($pressed);
    }

    protected function applyLabel(string $label): void
    {
        $this->record('applyLabel', $label);
    }

    protected function applyPressed(bool $pressed): void
    {
        $this->record('applyPressed', $pressed);
        $this->toggled($pressed);
    }
}
