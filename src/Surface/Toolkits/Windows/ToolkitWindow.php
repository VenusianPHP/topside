<?php

namespace Surface\Toolkits\Windows;

use Closure;
use Surface\Contracts\Toolkits\Primitives\PrimitiveFactory;
use Surface\Contracts\Toolkits\Primitives\PrimitiveRegistry;
use Surface\Contracts\Toolkits\Primitives\TKColumn;
use Surface\Contracts\Toolkits\Primitives\TKFixed;
use Surface\Contracts\Toolkits\Primitives\TKGrid;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive;
use Surface\Contracts\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Primitives\TKRow;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\ToolkitWindow as WindowContract;

/**
 * What every toolkit's window does the same way: the one content container, the registry
 * and the lookups. The driver's window supplies factory() and mountContent(), and calls
 * removeContent() from its close path while its native is still alive, so the tree is
 * destroyed before the window that holds it.
 */
abstract class ToolkitWindow implements WindowContract
{
    protected ?TKPrimitiveGroup $content_group = null;

    protected ?PrimitiveRegistry $registry = null;

    /**
     * Put the content container's native into the window's content area.
     */
    abstract protected function mountContent(TKPrimitiveGroup $content): void;

    public function registry(): PrimitiveRegistry
    {
        return $this->registry ??= new PrimitiveRegistry();
    }

    public function column(string $name, int $spacing = 0, int $padding = 0): TKColumn
    {
        return $this->mount($name, fn (PrimitiveFactory $factory) => $factory->mintColumn(null, $name, $spacing, $padding));
    }

    public function row(string $name, int $spacing = 0, int $padding = 0): TKRow
    {
        return $this->mount($name, fn (PrimitiveFactory $factory) => $factory->mintRow(null, $name, $spacing, $padding));
    }

    public function grid(string $name, int $spacing = 0, int $padding = 0): TKGrid
    {
        return $this->mount($name, fn (PrimitiveFactory $factory) => $factory->mintGrid(null, $name, $spacing, $padding));
    }

    public function fixed(string $name): TKFixed
    {
        return $this->mount($name, fn (PrimitiveFactory $factory) => $factory->mintFixed(null, $name));
    }

    public function content(): ?TKPrimitiveGroup
    {
        $this->guardOpen();

        return $this->content_group;
    }

    public function view(string $path): ?TKPrimitive
    {
        $this->guardOpen();

        return $this->registry()->byPath($path);
    }

    public function uuid(string $uuid): ?TKPrimitive
    {
        $this->guardOpen();

        return $this->registry()->byUuid($uuid);
    }

    public function forgetContent(TKPrimitiveGroup $content): void
    {
        if ($this->content_group !== $content) {
            throw new ToolkitRuntimeException("'{$content->path()}' is not the content of window '{$this->name()}'.");
        }
        if (! $content->isRemoved()) {
            throw new ToolkitRuntimeException("'{$content->path()}' is not removed; call its remove().");
        }
        $this->content_group = null;
    }

    /**
     * Admit, mint and mount the content container. Refusals happen before the factory mints.
     *
     * @template T of TKPrimitiveGroup
     * @param Closure(PrimitiveFactory): T $mint
     * @return T
     * @throws ToolkitRuntimeException When the window is closed, already has content, or the name is invalid.
     */
    protected function mount(string $name, Closure $mint): TKPrimitiveGroup
    {
        $this->guardOpen();
        PrimitiveRegistry::guardName($name);
        if (! is_null($this->content_group)) {
            throw new ToolkitRuntimeException("Window '{$this->name()}' already has content container '{$this->content_group->name()}'.");
        }

        $content = $mint($this->factory());
        $this->registry()->register($content);
        $this->content_group = $content;
        $this->mountContent($content);

        return $content;
    }

    /**
     * Remove the whole tree (terminal). Call from the close path while the native window lives.
     */
    protected function removeContent(): void
    {
        $this->content_group?->remove();
    }

    /**
     * @throws ToolkitRuntimeException When the window is closed.
     */
    protected function guardOpen(): void
    {
        if (! $this->isOpen()) {
            throw new ToolkitRuntimeException("Window '{$this->name()}' is closed.");
        }
    }
}
