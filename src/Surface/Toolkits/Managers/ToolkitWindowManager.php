<?php

namespace Surface\Toolkits\Managers;

use ReflectionException;
use Surface\Toolkits\Menus\MenuProfile;
use Surface\Contracts\Toolkits\ToolkitBridge;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Contracts\Toolkits\Services\Dialogs;
use Surface\Contracts\Toolkits\Services\Desktop;
use Surface\Contracts\Toolkits\Services\Clipboard;
use Surface\Contracts\Toolkits\Services\SystemAccent;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

class ToolkitWindowManager
{
    protected array $profiles = [];

    public function __construct(
        protected readonly ToolkitBridgeManager $toolkit_bridges,
        array $menus,
        protected readonly ?string $default_menu,
    ) {
        foreach ($menus as $name => $nodes)
        {
            $this->profiles[$name] = MenuProfile::parse($name, $nodes);
        }
    }

    /** With a parent, the window is a child of it (ToolkitWindowDriver::open()). */
    public function open(string $name, int $width, int $height, ?string $menu = null, ?ToolkitWindow $parent = null): ToolkitWindow
    {
        $driver = $this->driver();

        //$profiles = $this->default_menu === null ? null : $this->profile($this->default_menu);
        //$driver->setDefaultMenuBar($profiles);

        return $driver->open($name, $width, $height, $menu === null ? null : $this->profile($menu), $parent);
    }

    public function get(string $name): ?ToolkitWindow { return $this->driver()->get($name); }

    /** @return array<string, ToolkitWindow> */
    public function all(): array
    {
        return $this->driver()->all();
    }

    public function closeAll(): void
    {
        $this->driver()->closeAll();
    }

    /**
     * Set the state of a node of the default menu bar: the driver gets the configured default
     * profile first, as open() gives it.
     *
     * @throws ToolkitRuntimeException When config/windows.php names no default menu, and as the driver.
     */
    public function setItemEnabled(string $item, bool $enabled): void
    {
        $this->defaultBarDriver()->setItemEnabled($item, $enabled);
    }

    /** @throws ToolkitRuntimeException When config/windows.php names no default menu, and as the driver. */
    public function isItemEnabled(string $item): bool
    {
        return $this->defaultBarDriver()->isItemEnabled($item);
    }

    /** @throws ToolkitRuntimeException When config/windows.php names no default menu, and as the driver. */
    public function setToggle(string $item, bool $on): void
    {
        $this->defaultBarDriver()->setToggle($item, $on);
    }

    /** @throws ToolkitRuntimeException When config/windows.php names no default menu, and as the driver. */
    public function isToggled(string $item): bool
    {
        return $this->defaultBarDriver()->isToggled($item);
    }

    /** @throws ToolkitRuntimeException When config/windows.php names no default menu, and as the driver. */
    public function setRadio(string $group, string $item): void
    {
        $this->defaultBarDriver()->setRadio($group, $item);
    }

    /** @throws ToolkitRuntimeException When config/windows.php names no default menu, and as the driver. */
    public function radio(string $group): string
    {
        return $this->defaultBarDriver()->radio($group);
    }

    /** @throws ToolkitRuntimeException When config/windows.php names no default menu, and as the driver. */
    public function setItemLabel(string $item, string $label): void
    {
        $this->defaultBarDriver()->setItemLabel($item, $label);
    }

    /** @throws ToolkitRuntimeException When config/windows.php names no default menu, and as the driver. */
    public function itemLabel(string $item): string
    {
        return $this->defaultBarDriver()->itemLabel($item);
    }

    public function dialogs(): Dialogs { return $this->driver()->dialogs(); }

    public function clipboard(): Clipboard { return $this->driver()->clipboard(); }

    public function desktop(): Desktop { return $this->driver()->desktop(); }

    public function systemAccent(): SystemAccent { return $this->driver()->systemAccent(); }

    public function profile(string $name): MenuProfile
    {
        return $this->profiles[$name] ?? throw new ToolkitRuntimeException("No menu profile named '{$name}' in config/windows.php.");
    }

    protected function defaultBarDriver(): ToolkitBridge
    {
        if (is_null($this->default_menu)) {
            throw new ToolkitRuntimeException('config/windows.php names no default menu, so the default bar has no state.');
        }
        $driver = $this->driver();
        $driver->setDefaultMenuBar($this->profile($this->default_menu));

        return $driver;
    }

    /**
     * @throws ReflectionException
     */
    protected function driver(): ToolkitBridge
    {
        // The presentation config names the toolkit; null leaves the choice to the bridge manager's default.
        $driver = $this->toolkit_bridges->driver(
            config('main.presentation.toolkit.driver')
        );

        return $driver instanceof ToolkitBridge
            ? $driver
            : throw new ToolkitRuntimeException(get_class($driver).' does not open windows.');
    }
}