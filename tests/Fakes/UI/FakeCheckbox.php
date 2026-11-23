<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKCheckbox;

class FakeCheckbox extends TKCheckbox
{
    use FakeNative;

    /**
     * The native's state became $checked, as the toolkit's signal reports it: by the user,
     * or (on toolkits that signal it) by code.
     */
    public function nativeToggled(bool $checked): void
    {
        $this->toggled($checked);
    }

    protected function applyLabel(string $label): void
    {
        $this->record('applyLabel', $label);
    }

    protected function applyChecked(bool $checked): void
    {
        $this->record('applyChecked', $checked);
        // As GTK and Qt do: setting the state from code signals it too.
        $this->toggled($checked);
    }
}
