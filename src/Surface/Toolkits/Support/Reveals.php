<?php

namespace Surface\Toolkits\Support;

use Surface\Contracts\Toolkits\Primitives\TKPrimitive;

/**
 * Widgets a builder made hidden so that their appear transition plays when they are shown. Before
 * the window is presented they wait; show() makes them visible in the order they were added. After
 * that, a widget added (an overlay opened by a click, say) is shown at once.
 */
final class Reveals
{
    /** @var list<TKPrimitive> */
    private array $waiting = [];

    private bool $shown = false;

    public function add(TKPrimitive $widget): void
    {
        $this->shown ? $widget->setVisible(true) : $this->waiting[] = $widget;
    }

    /** Shows every waiting widget, and every widget added from now on. */
    public function show(): void
    {
        $this->shown = true;
        foreach ($this->waiting as $widget) {
            $widget->setVisible(true);
        }
        $this->waiting = [];
    }
}
