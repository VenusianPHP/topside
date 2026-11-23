<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKTabView;
use Surface\Toolkits\UI\TKPrimitive;

class FakeTabView extends TKTabView
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
        $this->chosen(0);
    }

    /** The user clicked the tab at $index. */
    public function nativeChosen(int $index): void
    {
        $this->chosen($index);
    }

    protected function applyTitle(TKPrimitive $page, string $title): void
    {
        $this->record('applyTitle', $page->name(), $title);
    }

    protected function applySelected(TKPrimitive $page): void
    {
        $this->record('applySelected', $page->name());
        $this->chosen(0);
    }
}
