<?php

namespace Surface\Contracts\Toolkits\UI;

use RuntimeException;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\Menus\ContextMenu;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;

/**
 * A native view inside a toolkit window. The toolkit owns layout; PHP declares
 * properties and reads sizes back. Every setter throws RuntimeException once removed.
 */
interface TKPrimitive
{
    /**
     * The name, unique among its siblings.
     * @return string
     */
    public function name(): string;

    /**
     * The dotted path from the window's content container, e.g. `main.toolbar.save`.
     * @return string
     */
    public function path(): string;

    /**
     * @return string uuid4
     */
    public function uuid(): string;

    /**
     * @return ToolkitPoweredWindow
     */
    public function window(): ToolkitPoweredWindow;

    /**
     * The container this primitive was created in; null for the content container.
     * @return TKPrimitiveGroup|null
     */
    public function parent(): ?TKPrimitiveGroup;

    /**
     * Where this primitive sits in its container: next, a grid cell, or its creation frame.
     * @return Placement
     */
    public function placement(): Placement;

    /**
     * @return bool
     */
    public function isRemoved(): bool;

    /**
     * Terminal: destroy the native and the subtree, free the names and uuids.
     * @return void
     * @throws RuntimeException When already removed.
     */
    public function remove(): void;

    /**
     * Native visibility; hiding a container hides its subtree. With an appear() transition set, showing
     * plays it; with a disappear() transition set, hiding plays it and the view hides when it lands.
     * isVisible() answers the new value at once either way.
     * @param bool $visible
     * @return $this
     */
    public function setVisible(bool $visible): static;

    /**
     * @return bool
     */
    public function isVisible(): bool;

    /**
     * @return $this
     */
    public function show(): static;

    /**
     * @return $this
     */
    public function hide(): static;

    /**
     * How opaque the view and its subtree draw: 0.0 (invisible, still laid out and hit) to 1.0.
     * @param float $opacity
     * @return $this
     * @throws RuntimeException When removed, or $opacity is outside 0.0..1.0.
     */
    public function setOpacity(float $opacity): static;

    /**
     * @return float
     */
    public function opacity(): float;

    /**
     * Play $transition each time setVisible(true) shows the view; null shows it at once.
     * @param TKTransition|null $transition
     * @param float $seconds how long it runs
     * @return $this
     * @throws RuntimeException When removed, or $seconds is negative.
     */
    public function appear(?TKTransition $transition, float $seconds = TKTransition::DEFAULT_SECONDS): static;

    /**
     * Play $transition backwards each time setVisible(false) hides the view, then hide it; null hides it at once.
     * @param TKTransition|null $transition
     * @param float $seconds how long it runs
     * @return $this
     * @throws RuntimeException When removed, or $seconds is negative.
     */
    public function disappear(?TKTransition $transition, float $seconds = TKTransition::DEFAULT_SECONDS): static;

    /**
     * Native sensitivity; containers cascade to their subtree.
     * @param bool $enabled
     * @return $this
     */
    public function setEnabled(bool $enabled): static;

    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * @return $this
     */
    public function enable(): static;

    /**
     * @return $this
     */
    public function disable(): static;

    /**
     * The native takes keyboard focus. FocusLost and FocusGained follow from the window's next
     * pump, as for any focus move.
     * @return $this
     * @throws RuntimeException When the view or a container above it is hidden
     *         ("not shown"), or disabled, or the native takes no focus.
     */
    public function focus(): static;

    /**
     * The native holds keyboard focus.
     */
    public function hasFocus(): bool;

    /**
     * The menu the toolkit opens where this view is right-clicked: a menu's nodes in the menu
     * profile's item format, a parsed menu (one shared by many views), or null to take it off.
     * A chosen item posts MenuActivated with its id, a toggle MenuToggled, a radio item
     * MenuRadioChosen. The menu keeps the user's toggle and radio choices: the next open shows them.
     * @param array|ContextMenu|null $menu
     * @return $this
     */
    public function setContextMenu(array|ContextMenu|null $menu): static;

    /** The menu set, with the user's toggle and radio choices since. */
    public function contextMenu(): ?ContextMenu;

    /**
     * Open a menu now at ($x, $y), points from the view's top-left (as RightClicked gives them).
     * An owned canvas press ends first, as for a right click. Choices post mail and are not kept:
     * pass the menu each time. On AppKit the call returns when the menu closes; on GTK and Qt at once.
     * @param array|ContextMenu $menu
     * @return $this
     * @throws RuntimeException When removed, or not shown: hidden, under a hidden group, or in a window not shown.
     */
    public function popupMenu(array|ContextMenu $menu, float $x, float $y): static;

    /**
     * The current allocation, read from the toolkit.
     * @return array{int, int} width, height
     */
    public function size(): array;

    /**
     * Opt in to (or out of) ViewResized mail for this view.
     * @param bool $on
     * @return $this
     */
    public function watchSize(bool $on = true): static;

    /**
     * @return bool
     */
    public function isWatchingSize(): bool;

    /**
     * Fill behind the view; null restores the toolkit's own.
     * @param Color|null $color
     * @return $this
     */
    public function setBackground(?Color $color): static;

    /**
     * Round the corners of the view's background (setBackground); its content is not clipped
     * unless setClip() says so. One radius for every corner, or one to four as CSS writes
     * them ('0 0 14 14' is square on top). 0 (the start) is square, except on a button: there
     * it is the toolkit's own button rounding. A toolkit that cannot round corners apart rounds
     * none of them when they differ. Posts nothing.
     * @param Corners|float|int|string|list<int|float> $radius in points
     * @return $this
     * @throws RuntimeException When a radius is negative or cannot be read.
     */
    public function setCornerRadius(Corners|float|int|string|array $radius): static;

    /**
     * The largest of the corner radii: the one radius when they are all the same.
     * @return float
     */
    public function cornerRadius(): float;

    /**
     * The radius of each corner.
     * @return Corners
     */
    public function corners(): Corners;

    /**
     * Cut what is inside the view at its edge, following its corner radius: an image that
     * fills a rounded container takes its rounded corners. Off at the start. A toolkit that
     * can clip only to the rectangle clips to the rectangle; one that cannot clip leaves it.
     * @param bool $clip
     * @return $this
     */
    public function setClip(bool $clip = true): static;

    /**
     * @return bool
     */
    public function clips(): bool;

    /**
     * Expand into spare space along each axis.
     * @param bool $horizontal
     * @param bool $vertical
     * @return $this
     */
    public function fill(bool $horizontal = true, bool $vertical = true): static;

    /**
     * Placement inside a slot larger than the view.
     * @param Align $horizontal
     * @param Align $vertical
     * @return $this
     */
    public function align(Align $horizontal, Align $vertical = Align::FILL): static;

    /**
     * The floor the toolkit never shrinks the view below.
     * @param int $width
     * @param int $height
     * @return $this
     * @throws RuntimeException When either is negative.
     */
    public function minSize(int $width, int $height): static;

    /**
     * The ceiling the toolkit never grows the view past; 0 is no ceiling that way. A view
     * that fills is as big as its room up to this, and sits by its alignment in the room
     * left over: fill and align center give a column no wider than this, centred.
     * @param int $width
     * @param int $height
     * @return $this
     * @throws RuntimeException When either is negative.
     */
    public function maxSize(int $width, int $height): static;

    /**
     * Play the appear transition now, as when the view is shown: for one just added to a
     * window already on screen. The toolkit is told it appears, though it was already
     * visible. Nothing when it is hidden or has no appear transition.
     * @return $this
     */
    public function playAppear(): static;

    /**
     * Space kept outside the view, between it and what is around it; its background does not
     * reach into it. A toolkit that cannot keep space around one view keeps none.
     * @param Insets|int|string|list<int> $margin one to four values, as CSS takes them
     * @return $this
     * @throws RuntimeException When a side is negative.
     */
    public function setMargin(Insets|int|string|array $margin): static;

    /**
     * @return Insets
     */
    public function margin(): Insets;

    /**
     * The pointer's shape over the view; null restores the toolkit's own.
     * @param Cursor|null $cursor
     * @return $this
     */
    public function setCursor(?Cursor $cursor): static;

    /**
     * @return Cursor|null
     */
    public function cursor(): ?Cursor;

    /**
     * Text shown when the pointer rests on the view; null or '' shows none.
     * @param string|null $tooltip
     * @return $this
     */
    public function setTooltip(?string $tooltip): static;

    /**
     * @return string|null
     */
    public function tooltip(): ?string;

    /**
     * A line round the view's edge, inside its background and its corner radius. 0 draws none.
     * @param int $width in points
     * @param Color|null $color null is the toolkit's own line colour
     * @return $this
     * @throws RuntimeException When the width is negative.
     */
    public function setBorder(int $width, ?Color $color = null): static;

    /**
     * @return int
     */
    public function borderWidth(): int;

    /**
     * @return Color|null
     */
    public function borderColor(): ?Color;

    /**
     * Move this primitive just before $sibling inside their column or row.
     * @param TKPrimitive $sibling
     * @return $this
     * @throws RuntimeException When the parent is not a column or row, $sibling is not a sibling, or it is this primitive.
     */
    public function moveBefore(TKPrimitive $sibling): static;

    /**
     * Move this primitive just after $sibling inside their column or row.
     * @param TKPrimitive $sibling
     * @return $this
     * @throws RuntimeException When the parent is not a column or row, $sibling is not a sibling, or it is this primitive.
     */
    public function moveAfter(TKPrimitive $sibling): static;

    /**
     * Move this primitive to display position $index inside its column or row.
     * @param int $index the position afterward, 0..count(children)-1
     * @return $this
     * @throws RuntimeException When the parent is not a column or row, or the index is out of range.
     */
    public function moveTo(int $index): static;
}
