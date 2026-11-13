<?php

namespace Surface\Contracts\Toolkits;

interface Composition
{
    /** The view's root name. Every widget path in the view starts with it: "welcome.continue". */
    public function name(): string;

    /** The text the window's history header shows while this view is open. */
    public function title(): string;

    /** Describe the layout. $ui is the view's root column. */
    public function compose(ViewBuilder $ui, array $args): void;
}