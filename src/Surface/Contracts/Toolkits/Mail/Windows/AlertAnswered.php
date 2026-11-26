<?php

namespace Surface\Contracts\Toolkits\Mail\Windows;

/**
 * The answer to ToolkitPoweredWindow::alert(), under the id it returned: the index of the
 * button, the cancel index for Esc and the alert's own close, -1 when the window closed
 * first. $suppressed: the "Don't ask again" box was ticked; false when the alert had none,
 * the toolkit shows none, or the window closed first.
 */
readonly class AlertAnswered extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public string $id,
        public int $button,
        public bool $suppressed = false,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "window.alert-answered.{$this->window}";
    }
}
