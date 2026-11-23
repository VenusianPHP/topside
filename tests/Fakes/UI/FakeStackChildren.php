<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKPrimitive;

/**
 * What a fake column and row share: children recorded by name as they go in, out and move.
 */
trait FakeStackChildren
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

    protected function reorderNative(TKPrimitive $child, int $index): void
    {
        $this->record('reorderNative', $child->name(), $index);
    }

    protected function applySpacing(int $spacing): void
    {
        $this->record('applySpacing', $spacing);
    }
}
