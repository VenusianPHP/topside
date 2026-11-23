<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKToggle;

class FakeToggle extends TKToggle
{
    use FakeNative;

    /** The native switch was flipped to $on. */
    public function nativeToggled(bool $on): void
    {
        $this->toggled($on);
    }

    protected function applyOn(bool $on): void
    {
        $this->record('applyOn', $on);
        $this->toggled($on);
    }
}
