<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKSplitView;
use Surface\Toolkits\UI\TKPrimitive;

class FakeSplitView extends TKSplitView
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

    /** The user dragged the divider. */
    public function nativeDragged(float $position): void
    {
        $this->dragged($position);
    }

    protected function applyPosition(float $position): void
    {
        $this->record('applyPosition', $position);
        $this->dragged($position + 0.1);
    }
}
