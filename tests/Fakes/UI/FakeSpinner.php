<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKSpinner;

class FakeSpinner extends TKSpinner
{
    use FakeNative;

    protected function applySpinning(bool $spinning): void
    {
        $this->record('applySpinning', $spinning);
    }
}
