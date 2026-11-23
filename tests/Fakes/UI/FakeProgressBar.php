<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKProgressBar;

class FakeProgressBar extends TKProgressBar
{
    use FakeNative;

    protected function applyFraction(?float $fraction): void
    {
        $this->record('applyFraction', $fraction);
    }
}
