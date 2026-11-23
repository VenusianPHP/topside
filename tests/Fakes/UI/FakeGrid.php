<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKGrid;
use Surface\Toolkits\UI\TKPrimitive;

class FakeGrid extends TKGrid
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

    protected function applySpacing(int $spacing): void
    {
        $this->record('applySpacing', $spacing);
    }

    protected function applyCells(): void
    {
        $this->record('applyCells');
    }
}
