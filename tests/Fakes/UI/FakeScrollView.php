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

    /** Where the fake view is; nativeScrolledTo() moves it as the user would. */
    public \Surface\Contracts\Toolkits\Styling\ScrollPosition $at;

    protected function nativeScrollPosition(): \Surface\Contracts\Toolkits\Styling\ScrollPosition
    {
        return $this->at ??= new \Surface\Contracts\Toolkits\Styling\ScrollPosition(0.0, 0.0, 1.0, 0.25);
    }

    protected function applyWatchScroll(bool $on): void
    {
        $this->record('applyWatchScroll', $on);
    }

    /** The user scrolled the native to $y, a quarter of the content showing. */
    public function nativeScrolledTo(float $y): void
    {
        $this->at = new \Surface\Contracts\Toolkits\Styling\ScrollPosition(0.0, $y, 1.0, 0.25);
        $this->scrolled();
    }
}
