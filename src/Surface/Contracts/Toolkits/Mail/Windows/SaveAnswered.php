<?php

namespace Surface\Contracts\Toolkits\Mail\Windows;

/**
 * The answer to ToolkitPoweredWindow::saveFile(), under the id it returned: the path the user
 * chose, or null when they cancelled or the window closed first.
 */
readonly class SaveAnswered extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public string $id,
        public ?string $path,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "window.save-answered.{$this->window}";
    }
}
