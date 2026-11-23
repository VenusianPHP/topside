<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKScrollView;
use Surface\Toolkits\UI\TKPrimitive;

class FakeScrollView extends TKScrollView
{
    use FakeNative;

    protected function attachNative(TKPrimitive $child): void
    {
        $this->record('attachNative', $child->name());
    }

    protected function detachNative(TKPrimitive $child): void
    {
        $this->record('detachNative', $child->name());
    }

    protected function applyScrollbars(bool $horizontal, bool $vertical): void
    {
        $this->record('applyScrollbars', $horizontal, $vertical);
    }
}
