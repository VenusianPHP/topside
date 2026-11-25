<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKOverlay;
use Surface\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\Insets;

class FakeOverlay extends TKOverlay
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

    protected function applyPadding(Insets $padding): void
    {
        $this->record('applyPadding', $padding);
    }

    /**
     * The children that give the overlay its natural size, by name: what a toolkit measures.
     *
     * @return list<string>
     */
    public function sizingChildren(): array
    {
        return array_values(array_map(
            static fn (TKPrimitive $child): string => $child->name(),
            array_filter($this->children(), fn (TKPrimitive $child): bool => $this->sizesOverlay($child)),
        ));
    }

    /** Whether it takes spare room along an axis in its parent. */
    public function expandsAlong(bool $horizontal): bool
    {
        return $this->expands($horizontal);
    }
}
