<?php

namespace Surface\Contracts\Toolkits\Menus;

/**
 * A toolkit's menu bar: one per window, and on macOS one more for when no window is focused.
 * It is told single operations and never given a bar to rebuild, so a menu the user has open
 * stays open. None of them posts mail.
 */
interface TKMenuBar
{
    /**
     * Make the native for $node at $index under the node $parent, or in the bar when null.
     * What is inside a menu is inserted after it.
     */
    public function insert(?string $parent, int $index, BarNode $node): void;

    /**
     * Remove a node and everything under it.
     */
    public function remove(string $uuid): void;

    /**
     * Put a node at $index under $parent, or in the bar when null.
     */
    public function move(string $uuid, ?string $parent, int $index): void;

    /**
     * @param array<string, mixed> $changes of label, enabled, on, chosen and shortcut, what changed
     */
    public function change(string $uuid, array $changes): void;

    /**
     * Make this the visible bar where one bar is shared; nothing where each window draws its own.
     */
    public function show(): void;

    /**
     * Drop every native. The bar is not used again.
     */
    public function release(): void;

    public function supports(StandardKind $kind): bool;
}
