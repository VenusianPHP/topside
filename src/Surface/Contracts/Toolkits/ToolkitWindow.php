<?php

namespace Surface\Contracts\Toolkits;

use Surface\Contracts\Core\NativeOSWindow;
use Surface\Contracts\Toolkits\Primitives\PrimitiveFactory;
use Surface\Contracts\Toolkits\Primitives\PrimitiveRegistry;
use Surface\Contracts\Toolkits\Primitives\TKColumn;
use Surface\Contracts\Toolkits\Primitives\TKFixed;
use Surface\Contracts\Toolkits\Primitives\TKGrid;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive;
use Surface\Contracts\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Primitives\TKRow;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

interface ToolkitWindow extends NativeOSWindow
{
    public function name(): string;

    /**
     *  Destroy the native window. Terminal: the handle is dead afterward and
     *  WindowClosed is posted, exactly as when the user closes it.
     *
     * @return void
     */
    public function close(): void;

    /**
     * @return bool
     */
    public function isOpen(): bool;

    /**
     * @return string
     */
    public function title(): string;

    /**
     * Show and focus. The first present maps the window.
     *
     * @return $this
     */
    public function present(): static;

    /**
     * @param string $item
     * @return bool
     */
    public function isToggled(string $item): bool;

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): static;

    /**
     * @param string $profile
     * @return $this
     * @throws ToolkitRuntimeException When no profile is registered under that name, or the window is a child.
     */
    public function setMenuBar(string $profile): static;

    /**
     * Flip a toggle item's state. Posts nothing: the app changed it, it knows.
     *
     * @param string $item
     * @param bool $on
     * @return $this
     */
    public function setToggle(string $item, bool $on): static;

    /**
     * Set the state of a node in this window's menu bar. A node that is not enabled, or that is
     * below a disabled folder, shows as disabled; such an item posts nothing and its accelerator
     * does nothing. The menu is not rebuilt. Posts nothing.
     *
     * @param string $item the id of an item or a folder
     * @param bool $enabled
     * @return $this
     * @throws ToolkitRuntimeException When the window has no menu bar, no node has the id, or the node
     *                         cannot take a state (an edit role, a folder that holds an edit role,
     *                         the first folder).
     */
    public function setItemEnabled(string $item, bool $enabled): static;

    /**
     * The state of the node, from the profile or from setItemEnabled(). The folders above the
     * node do not change the value.
     *
     * @param string $item
     * @return bool
     * @throws ToolkitRuntimeException When the window has no menu bar, no node has the id, or the node
     *                         takes no state (an edit role, a folder that holds one, the first folder).
     */
    public function isItemEnabled(string $item): bool;

    /**
     * Make $item the one on in its radio group of this window's menu bar. Posts nothing; the
     * user's choice posts MenuRadioChosen.
     *
     * @param string $group
     * @param string $item
     * @return $this
     * @throws ToolkitRuntimeException When the window has no menu bar, no radio group has the name, or the item is not in it.
     */
    public function setRadio(string $group, string $item): static;

    /**
     * The item on in $group of this window's menu bar.
     *
     * @param string $group
     * @return string
     * @throws ToolkitRuntimeException When the window has no menu bar or no radio group has the name.
     */
    public function radio(string $group): string;

    /**
     * Relabel a node of this window's menu bar, a folder or an item. Posts nothing.
     *
     * @param string $item
     * @param string $label
     * @return $this
     * @throws ToolkitRuntimeException When the window has no menu bar or no node has the id.
     */
    public function setItemLabel(string $item, string $label): static;

    /**
     * @param string $item
     * @return string
     * @throws ToolkitRuntimeException When the window has no menu bar or no node has the id.
     */
    public function itemLabel(string $item): string;

    /**
     * The window this child is modal to; null for a top-level window.
     *
     * @return \Surface\Contracts\Toolkits\ToolkitWindow|null
     */
    public function parent(): ?ToolkitWindow;

    /**
     * While on, the user's close (the title-bar button; Alt+F4 on Linux; Esc in a child with no
     * cancel button) posts WindowCloseRequested and the window stays open. close() always
     * closes. Posts nothing.
     *
     * @param bool $on
     * @return $this
     */
    public function confirmClose(bool $on = true): static;

    /**
     * @return bool
     */
    public function isConfirmingClose(): bool;

    /**
     * The primitive whose native holds keyboard focus; null when none does, or when the holder
     * is a native Surface did not make.
     */
    public function focused(): ?TKPrimitive;

    /**
     * The primitive that takes focus on each present(); null takes it off. Posts nothing.
     * Key precedence while the window is key: a menu accelerator, then the Return and Esc of a
     * default or cancel button, then Tab and Shift-Tab moving focus (unless a focused canvas
     * takes Tab), then the focused native. A key that passes them all posts WindowKeyPressed.
     *
     * @return $this
     */
    public function setInitialFocus(?TKPrimitive $primitive): static;

    /**
     * Declare the window's one content container as a column. Every other primitive is
     * created on a container.
     *
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKColumn
     * @throws ToolkitRuntimeException When the window already has content, is closed, or the name is invalid.
     */
    public function column(string $name, int $spacing = 0, int $padding = 0): TKColumn;

    /**
     * Declare the window's one content container as a row.
     *
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKRow
     * @throws ToolkitRuntimeException When the window already has content, is closed, or the name is invalid.
     */
    public function row(string $name, int $spacing = 0, int $padding = 0): TKRow;

    /**
     * Declare the window's one content container as a grid.
     *
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKGrid
     * @throws ToolkitRuntimeException When the window already has content, is closed, or the name is invalid.
     */
    public function grid(string $name, int $spacing = 0, int $padding = 0): TKGrid;

    /**
     * Declare the window's one content container as a fixed (pixel-framed) group.
     *
     * @param string $name
     * @return TKFixed
     * @throws ToolkitRuntimeException When the window already has content, is closed, or the name is invalid.
     */
    public function fixed(string $name): TKFixed;

    /**
     * The one content container, once declared.
     *
     * @return TKPrimitiveGroup|null
     * @throws ToolkitRuntimeException When the window is closed.
     */
    public function content(): ?TKPrimitiveGroup;

    /**
     * Look a primitive up by dotted path from the content container: `main.toolbar.save`.
     *
     * @param string $path
     * @return TKPrimitive|null
     * @throws ToolkitRuntimeException When the window is closed.
     */
    public function view(string $path): ?TKPrimitive;

    /**
     * Look a primitive up by uuid.
     *
     * @param string $uuid
     * @return TKPrimitive|null
     * @throws ToolkitRuntimeException When the window is closed.
     */
    public function uuid(string $uuid): ?TKPrimitive;

    /**
     * The content area, read from the toolkit.
     *
     * @return array{int, int} width, height
     * @throws ToolkitRuntimeException When the window is closed.
     */
    public function size(): array;

    /**
     * This window's path and uuid index. For primitives and their containers.
     *
     * @return PrimitiveRegistry
     */
    public function registry(): PrimitiveRegistry;

    /**
     * The driver's minter. For containers.
     *
     * @return PrimitiveFactory
     */
    public function factory(): PrimitiveFactory;

    /**
     * Clear the content slot. Called by the content container's remove().
     *
     * @param TKPrimitiveGroup $content
     * @return void
     * @throws ToolkitRuntimeException When $content is not this window's content or is not removed.
     */
    public function forgetContent(TKPrimitiveGroup $content): void;
}