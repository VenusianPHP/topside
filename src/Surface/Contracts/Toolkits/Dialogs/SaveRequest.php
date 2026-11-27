<?php

namespace Surface\Contracts\Toolkits\Dialogs;

/**
 * What a save panel asks for: ToolkitPoweredWindow::saveFile(). Each toolkit shows it with its
 * own save panel, modal to the window, and asks itself before replacing a file that is there.
 */
final readonly class SaveRequest
{
    /**
     * @param string|null $name the file name the panel suggests
     * @param string|null $directory the folder it opens in; null: the toolkit's own choice
     * @param string|null $title the panel's title, where the toolkit shows one
     */
    public function __construct(
        public ?string $name = null,
        public ?string $directory = null,
        public ?string $title = null,
    ) {}
}
