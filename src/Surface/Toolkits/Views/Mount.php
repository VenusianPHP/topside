<?php

namespace Surface\Toolkits\Views;

use Closure;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;

/**
 * What a view is mounted in, given to the component and every one inside it: the window's
 * navigation, the window, how to show the view again when its state changes outside a
 * handler, and how to ask the window for an alert whose answer runs a handler of the view.
 */
final readonly class Mount
{
    /**
     * @param Navigator|null $navigator the window's navigation
     * @param ToolkitPoweredWindow|null $window the window the view is in
     * @param (Closure(): void)|null $refresh renders the view again while it is on screen
     * @param (Closure(\Surface\Contracts\Toolkits\Dialogs\Alert, Closure): string)|null $alert shows an
     *        alert on the window and runs the handler with its answer while the view is on screen
     */
    public function __construct(
        public ?Navigator $navigator = null,
        public ?ToolkitPoweredWindow $window = null,
        public ?Closure $refresh = null,
        public ?Closure $alert = null,
    ) {}
}
