<?php

namespace Surface\Contracts\Toolkits;

interface ToolkitPoweredWindow
{
    public function name(): string;

    /**
     * Show and focus. The first present maps the window.
     *
     * @return $this
     */
    public function present(): static;

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): static;

    /**
     * The toolkit's own window object (or handle), for engines that draw into it.
     */
    public function native(): mixed;

    /**
     * The content area: what the content container is laid out in.
     *
     * @return array{int, int} width, height
     */
    public function size(): array;

    public function isOpen(): bool;

    /**
     * Make the window's content container, a column filling its content area. A window has
     * one; every other primitive is made inside it.
     *
     * @return \Surface\Contracts\Toolkits\UI\TKColumn
     * @throws \RuntimeException When the window is closed, already has one, the name is
     *         refused, or the toolkit has no column.
     */
    public function column(string $name, int $spacing = 0, \Surface\Contracts\Toolkits\UI\Insets|int $padding = 0): \Surface\Contracts\Toolkits\UI\TKColumn;

    /**
     * The content container; null before column(), and once it is removed.
     */
    public function content(): ?\Surface\Contracts\Toolkits\UI\TKColumn;

    /**
     * Called by the content container's remove(): it is gone from the window. Not for apps.
     */
    public function contentRemoved(\Surface\Contracts\Toolkits\UI\TKPrimitive $content): void;

    /**
     * This window's toolkit class for a kind of primitive, by its contract; null when it has none.
     *
     * @param class-string $contract
     * @return class-string|null
     */
    public function primitiveFor(string $contract): ?string;

    /**
     * The primitive that takes the focus on each present(); null takes it off. Posts nothing.
     *
     * @return $this
     * @throws \RuntimeException When the primitive is in another window.
     */
    public function setInitialFocus(?\Surface\Contracts\Toolkits\UI\TKPrimitive $primitive): static;

    /**
     * The primitive that is the window's focus; null when none is.
     */
    public function focused(): ?\Surface\Contracts\Toolkits\UI\TKPrimitive;

    /**
     * Send mail about this window, or something in it, to the application.
     */
    public function post(object $mail): void;

    /**
     * Mail that replaces what still waits under $key, posted when the pump returns.
     */
    public function postLatest(string $key, object $mail): void;

    /**
     * Close from code; a window that confirms close closes too.
     */
    public function close(): void;

    /**
     * While on, the user's close posts WindowCloseRequested and the window stays open.
     *
     * @return $this
     */
    public function confirmClose(bool $on = true): static;


}