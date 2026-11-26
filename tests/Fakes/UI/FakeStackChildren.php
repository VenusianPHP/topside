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

    protected function childLayoutChanged(TKPrimitive $child): void
    {
        $this->record('childLayoutChanged', $child->name());
    }

    protected function applySpacing(int $spacing): void
    {
        $this->record('applySpacing', $spacing);
    }

    protected function applyPadding(\Surface\Contracts\Toolkits\UI\Insets $padding): void
    {
        $this->record('applyPadding', $padding);
    }

    protected function applyHomogeneous(bool $on): void
    {
        $this->record('applyHomogeneous', $on);
    }
}
