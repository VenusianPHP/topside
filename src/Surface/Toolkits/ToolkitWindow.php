<?php

namespace Surface\Toolkits;

use Surface\Toolkits\UI\TKPrimitive as TKPrimitiveBase;
use WeakReference;
use Surface\Toolkits\Managers\MenuManager;
use Surface\Contracts\Toolkits\UI\TKColumn as ColumnContract;
use Surface\Contracts\Toolkits\UI\TKPrimitive as PrimitiveContract;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\ToolkitAdapterDriver;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Windows\WindowClosed;
use Surface\Contracts\Toolkits\Mail\Windows\WindowResized;
use Surface\Contracts\Toolkits\Mail\Windows\WindowCloseRequested;
use Surface\Contracts\Toolkits\Mail\Views\FocusLost;
use Surface\Contracts\Toolkits\Mail\Views\FocusGained;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup as GroupContract;

/**
 * The window's lifecycle, the same for every toolkit: an adapter builds the native window in
 * bootstrapWindow() and wires its signals to the methods here.
 *
 *  - the user's close (title bar, Cmd+W)  → allowsUserClose(), then closed() if it goes through
 *  - code's close                         → close(), which runs the toolkit's own close path
 *  - the native window is gone            → closed(): the one close path
 *  - a resize                             → resized($width, $height)
 *  - the focus came, or went              → gainedFocus(), lostFocus(), in that callback
 *  - after every pump                     → settle(), for what the toolkit has no callback for
 */
abstract class ToolkitWindow implements ToolkitPoweredWindow
{
    protected ?ColumnContract $content_group = null;

    /** @var WeakReference<TKPrimitive>|null focused on each present(); weak, so a removed primitive is freed */
    protected ?WeakReference $initial_focus = null;

    /** @var WeakReference<PrimitiveContract>|null the primitive last seen holding the window's focus */
    private ?WeakReference $focus_holder = null;

    /**
     * The user's close asks the app (WindowCloseRequested) instead of closing.
     */
    protected bool $confirming = false;

    /**
     * close() runs: its own close request goes through while the window confirms close.
     */
    protected bool $closing = false;

    public function __construct(
        protected readonly string $name,
        protected readonly ToolkitSession $session,
        protected readonly ToolkitAdapterDriver $adapter,
        int $width,
        int $height,
        protected readonly ?ToolkitPoweredWindow $parent = null,
    ) {
        $this->bootstrapWindow($width, $height);
        $this->session->watchWindow($this);
    }

    abstract protected function bootstrapWindow(int $width, int $height): void;

    /**
     * Whether the native window still lives.
     */
    abstract public function isOpen(): bool;

    /**
     * Close the native window through the toolkit's own close path, so its signal reaches
     * closed(). Runs with $closing set: a confirming window closes anyway.
     */
    abstract protected function closeNative(): void;

    /**
     * Drop the native window. Called by closed() once the content tree is gone; isOpen() is
     * false afterwards.
     */
    abstract protected function releaseNative(): void;

    /**
     * Put the content container's native in the window's content area, filling it.
     */
    abstract protected function mountContent(ColumnContract $content): void;

    /**
     * Take the content container's native out of the content area. It is destroyed right after.
     */
    abstract protected function unmountContent(ColumnContract $content): void;

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Built from the toolkit's column class (its driver's primitive map), then placed in the
     * content area by mountContent().
     *
     * @throws ToolkitException When the window is closed, already has a content container,
     *         or the toolkit has no column. The name is checked by the column itself.
     */
    public function column(string $name, int $spacing = 0, \Surface\Contracts\Toolkits\UI\Insets|int $padding = 0): ColumnContract
    {
        if (! $this->isOpen()) {
            throw new ToolkitException("Window '{$this->name}' is closed.");
        }
        if (! is_null($this->content_group)) {
            throw new ToolkitException("Window '{$this->name}' already has content container '{$this->content_group->name()}'.");
        }

        $class = $this->primitiveFor(ColumnContract::class)
            ?? throw new ToolkitException("This toolkit has no TKColumn: window '{$this->name}' has nothing to hold its content.");

        $column = new $class($name, $this, null, null, $spacing, $padding);
        $this->content_group = $column;
        $this->mountContent($column);

        return $column;
    }

    public function content(): ?ColumnContract
    {
        return $this->content_group;
    }

    /**
     * The content container is being removed: its native leaves the content area before it
     * is destroyed, as a child's leaves its container.
     */
    public function contentRemoved(PrimitiveContract $content): void
    {
        if ($content !== $this->content_group) {
            return;
        }

        $this->unmountContent($content);
        $this->content_group = null;
    }

    public function primitiveFor(string $contract): ?string
    {
        return $this->adapter->primitiveFor($contract);
    }

    /**
     * Focused on each present(), through its own focus(); null takes it off. Posts nothing.
     *
     * @throws ToolkitException When $primitive is in another window.
     */
    public function setInitialFocus(?PrimitiveContract $primitive): static
    {
        if (! is_null($primitive) && $primitive->window() !== $this) {
            throw new ToolkitException("'{$primitive->path()}' is not in window '{$this->name}'.");
        }

        $this->initial_focus = is_null($primitive) ? null : WeakReference::create($primitive);

        return $this;
    }

    /**
     * The primitive that is the window's focus: the deepest shown one whose hasFocus() says so;
     * null when none is (the focus is on no primitive, or on a native topside did not make).
     */
    public function focused(): ?PrimitiveContract
    {
        return $this->holderIn($this->content_group);
    }

    /**
     * After every pump: when the window's focus moved to another primitive, FocusLost for the
     * one that had it (unless it was removed) and FocusGained for the one that has it. Every
     * move posts, a click, Tab, focus() from code, or the toolkit's own (hiding or disabling a
     * focused primitive). Moves within one pump post once, from the first holder to the last.
     */
    public function settleFocus(): void
    {
        if (! $this->isOpen()) {
            return;
        }

        $now = $this->focused();
        $last = $this->focus_holder?->get();
        if ($now === $last) {
            return;
        }

        $this->focus_holder = is_null($now) ? null : WeakReference::create($now);

        if (! is_null($last) && ! $last->isRemoved()) {
            if ($last instanceof TKPrimitiveBase) {
                $last->focusMovedAway();
            }
            $this->post(new FocusLost($this->name, $last->path(), $last->uuid()));
        }
        if (! is_null($now)) {
            $this->post(new FocusGained($this->name, $now->path(), $now->uuid()));
        }
    }

    /**
     * For present(), once the window is shown: the initial focus takes the focus, when it can.
     * A primitive that is hidden, disabled or takes no focus is left as it is.
     */
    protected function focusInitial(): void
    {
        $initial = $this->initial_focus?->get();
        if (is_null($initial) || $initial->isRemoved()) {
            return;
        }

        try {
            $initial->focus();
        } catch (ToolkitException) {
        }
    }

    public function post(object $mail): void
    {
        $this->session->post($mail);
    }

    /**
     * Post $mail once the pump returns, instead of any still waiting under $key: a drag's
     * moves become one piece of mail. AppKit keeps the pump until the mouse is released, so
     * there that is one for the whole drag.
     */
    public function postLatest(string $key, object $mail): void
    {
        $this->session->postLatest($key, $mail);
    }

    /**
     * Close from code. A confirming window closes too: the app already decided.
     */
    public function close(): void
    {
        if (! $this->isOpen()) {
            return;
        }

        $this->closing = true;
        try {
            $this->closeNative();
        } finally {
            $this->closing = false;
        }
    }

    /**
     * While on, the user's close posts WindowCloseRequested and the window stays: the app
     * closes it with close() once it decides.
     */
    public function confirmClose(bool $on = true): static
    {
        $this->confirming = $on;

        return $this;
    }

    public function isConfirmingClose(): bool
    {
        return $this->confirming;
    }

    /**
     * End of a pump. Override for what the toolkit reports no callback for (activation, size).
     */
    public function settle(): void {}

    /**
     * The user asked to close. True lets the toolkit close it; false keeps it open, and a
     * confirming window posts WindowCloseRequested for the app to decide.
     */
    protected function allowsUserClose(): bool
    {
        if ($this->confirming && ! $this->closing) {
            $this->session->post(new WindowCloseRequested($this->name));

            return false;
        }

        return true;
    }

    /**
     * The window became the focused one. Call it from the toolkit's own callback: its menu
     * bar is shown then and there, where one bar is shared.
     */
    protected function gainedFocus(): void
    {
        app(MenuManager::class)->gainedFocus($this->name);
    }

    /**
     * The window stopped being the focused one, from the toolkit's own callback.
     */
    protected function lostFocus(): void
    {
        app(MenuManager::class)->lostFocus($this->name);
    }

    /**
     * Run before the content tree goes, while the native window is whole: what the toolkit
     * needs undone first.
     */
    protected function beforeClose(): void {}

    /**
     * The one close path, while the native window still lives: the content tree goes, the
     * session stops tracking the window (and drops a resize not yet sent), the native window
     * is dropped, WindowClosed is posted and the driver forgets the name. Safe to call twice.
     */
    protected function closed(): void
    {
        if (! $this->isOpen()) {
            return;
        }

        $this->beforeClose();
        app(MenuManager::class)->closed($this->name);
        $this->removeContent();
        $this->session->unwatchWindow($this);
        $this->releaseNative();
        $this->session->post(new WindowClosed($this->name));
        $this->adapter->forget($this->name);
    }

    /**
     * Children first, so the deepest primitive that holds the focus is found. A hidden
     * primitive, and everything inside it, holds none: it takes no keys. (Qt, for one, keeps a
     * hidden widget as its window's focus widget while the window is not the active one.)
     */
    private function holderIn(?PrimitiveContract $primitive): ?PrimitiveContract
    {
        if (is_null($primitive) || $primitive->isRemoved() || ! $primitive->isVisible()) {
            return null;
        }

        if ($primitive instanceof GroupContract) {
            foreach ($primitive->children() as $child) {
                $holder = $this->holderIn($child);
                if (! is_null($holder)) {
                    return $holder;
                }
            }
        }

        return $primitive->hasFocus() ? $primitive : null;
    }

    /**
     * The content area's new size. A burst within one pump is one WindowResized, with the last size.
     */
    protected function resized(int $width, int $height): void
    {
        $this->session->postLatest(ToolkitSession::resizedKey($this->name), new WindowResized($this->name, $width, $height));
    }

    /**
     * Remove the whole tree (terminal). Call from the close path while the native window lives.
     */
    protected function removeContent(): void
    {
        $this->content_group?->remove();
    }
}
