<?php

namespace Surface\Toolkits\Menus;

/**
 * What the toolkit's own About panel shows. Only the name is required; a toolkit leaves out
 * what it is not given.
 */
final readonly class AboutPanel
{
    use CopiesItself;

    /**
     * @throws MenuException When the name is empty.
     */
    public function __construct(
        public string $name,
        public ?string $version = null,
        public ?string $copyright = null,
        public ?string $credits = null,
    ) {
        if ($name === '') {
            throw new MenuException('An About panel needs the application\'s name.');
        }
    }
}
