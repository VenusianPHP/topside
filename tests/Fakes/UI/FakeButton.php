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
}
