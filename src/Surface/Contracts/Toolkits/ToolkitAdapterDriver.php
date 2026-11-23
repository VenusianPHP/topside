<?php

namespace Surface\Contracts\Toolkits;

use Surface\Contracts\Toolkits\Menus\TKMenuBar;

interface ToolkitAdapterDriver
{
    public function connect(): ToolkitSession;

    public function session(): ?ToolkitSession;

    public function forget(string $name): void;

    /**
     * A new menu bar for $window, or for when no window is focused; null when the toolkit has none.
     */
    public function menuBar(?ToolkitPoweredWindow $window = null): ?TKMenuBar;

    public function open(string $name, int $width, int $height, ?ToolkitPoweredWindow $parent = null): ToolkitPoweredWindow;

    /**
     * This toolkit's class for a kind of primitive, by its contract (TKColumn, TKButton, ...);
     * null when the toolkit has none.
     *
     * @param class-string $contract
     * @return class-string|null
     */
    public function primitiveFor(string $contract): ?string;
}