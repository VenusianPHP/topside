<?php

namespace Surface\Toolkits\Menus\Runtime;

use Voyager\Contracts\Signals\NamedSignal;

/**
 * From a toolkit's native callback to the loop: a node was chosen. Its callback runs, and its
 * public mail is posted, when this is delivered.
 *
 * @internal
 */
final readonly class MenuNodeActivated implements NamedSignal
{
    /**
     * @param string|null $window null for the bar shown when no window is focused
     */
    public function __construct(
        public ?string $window,
        public string $uuid,
        public bool|string|null $value = null,
    ) {}

    public function name(): string
    {
        return 'menu.activated';
    }
}
