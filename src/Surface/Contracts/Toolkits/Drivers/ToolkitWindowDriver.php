<?php

namespace Surface\Contracts\Toolkits\Drivers;

use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Contracts\Toolkits\Menus\MenuProfile;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

interface ToolkitWindowDriver
{
    /**
     * With a parent, the window is a child: modal to the parent, with no menu bar. Closing the
     * parent closes the child first.
     *
     * @param string $name
     * @param int $width
     * @param int $height
     * @param MenuProfile|null $menu
     * @param ToolkitWindow|null $parent
     * @return ToolkitWindow
     * @throws ToolkitRuntimeException When the name is taken or the toolkit refuses; and with a parent:
     *                         when the parent is closed, is not a window of this driver, is a
     *                         child, or has an open child, or $menu is not null.
     */
    public function open(string $name, int $width, int $height, ?MenuProfile $menu = null, ?ToolkitWindow $parent = null): ToolkitWindow;

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * @param string $name
     * @return ToolkitWindow|null
     */
    public function get(string $name): ?ToolkitWindow;

    /**
     * The open windows, by name.
     *
     * @return array<string, ToolkitWindow>
     */
    public function all(): array;

    /**
     * Close every open window, as close() on each.
     *
     * @return void
     */
    public function closeAll(): void;
}
