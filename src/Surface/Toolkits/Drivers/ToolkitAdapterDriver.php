<?php

namespace Surface\Toolkits\Drivers;

use RuntimeException;
use ReflectionException;
use Surface\Toolkits\ToolkitSession;
use Voyager\Contracts\Core\FrameworkCore;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Surface\Contracts\Toolkits\Menus\TKMenuBar;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\ToolkitAdapterDriver as ToolkitAdapterContract;

abstract class ToolkitAdapterDriver implements ToolkitAdapterContract
{
    protected array $windows = [];

    protected ?ToolkitSession $session = null;

    /**
     * The window class this toolkit opens. Built as new $window($name, $session, $this,
     * $width, $height, $parent); override conjureWindow() for anything else.
     *
     * @var class-string<ToolkitPoweredWindow>
     */
    protected string $window;

    /**
     * This toolkit's primitive classes, by the contract each implements: the one place that
     * says which class is a column, a button, ... for this toolkit. Containers and the window's
     * content container are built from it.
     *
     * @var array<class-string, class-string>
     */
    protected array $primitives = [];

    /**
     * This toolkit's menu bar class, built as new $menu_bar($session, $window). Null while it
     * has none: menus are then worked out and nothing is shown.
     *
     * @var class-string<TKMenuBar>|null
     */
    protected ?string $menu_bar = null;

    public function __construct(
        protected FrameworkCore $app
    ) {}

    abstract protected function instanceSession(): void;

    protected function connected(): void {}

    protected function conjureWindow(string $name, int $width, int $height, ToolkitSession $session, ?ToolkitPoweredWindow $parent = null): ToolkitPoweredWindow
    {
        return new $this->window($name, $session, $this, $width, $height, $parent);
    }

    public function primitiveFor(string $contract): ?string
    {
        return $this->primitives[$contract] ?? null;
    }

    public function menuBar(?ToolkitPoweredWindow $window = null): ?TKMenuBar
    {
        return is_null($this->menu_bar) ? null : new $this->menu_bar($this->session, $window);
    }

    public function session(): ?ToolkitSession
    {
        return $this->session;
    }

    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function connect(): ToolkitSession
    {
        $this->instanceSession();
        $this->session->connect();
        $this->connected();

        return $this->session;
    }

    public function forget(string $name): void
    {
        unset($this->windows[$name]);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function open(string $name, int $width, int $height, $parent = null): ToolkitPoweredWindow
    {
        if (isset($this->windows[$name])) {
            throw new RuntimeException("A window named '{$name}' is already open.");
        }

        if (! is_null($parent)) {
            // @todo - something about child windows
        }

        $session = $this->connect();

        return $this->windows[$name] = $this->conjureWindow($name, $width, $height, $session, $parent);
    }
}
