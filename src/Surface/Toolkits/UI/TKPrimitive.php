<?php

namespace Surface\Toolkits\UI;

use Closure;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\ToolkitException;
use InvalidArgumentException;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\UI\Cursor;
use Surface\Contracts\Toolkits\UI\Corners;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\UI\TKTransition;
use Surface\Contracts\Toolkits\Menus\ContextMenu;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\ViewResized;
use Surface\Contracts\Toolkits\UI\TKPrimitive as PrimitiveContract;

/**
 * What every toolkit's primitive does the same way: its place in the tree, the state PHP
 * declares (visible, enabled, opacity, ...) and the surrounding rules. The toolkit's side is
 * the hooks at the bottom: an adapter's primitive builds its native in bootstrapNative()
 * and answers each apply*() by setting that one thing on the native.
 *
 * The state here is the truth, not the native's: a getter never asks the toolkit, so it
 * answers the same on every toolkit and at once after a setter.
 *
 * Left to the adapter from the contract: hasFocus() and size().
 *
 * The hooks with a body (margin, cursor, tooltip, border) do nothing until an adapter
 * overrides them: a toolkit with no native way to show a setting leaves it unshown, and the
 * getter still answers what was set.
 */
abstract class TKPrimitive implements PrimitiveContract
{
    private readonly string $uuid;

    private readonly Placement $placement;

    private bool $removed = false;

    private bool $visible = true;

    private bool $enabled = true;

    private float $opacity = 1.0;

    private ?Corners $corners = null;

    private bool $clip = false;

    private bool $watching_size = false;

    /** @var array{int, int}|null the size the last ViewResized carried, while watching */
    private ?array $posted_size = null;

    private ?Insets $margin = null;

    private ?Cursor $cursor = null;

    private ?string $tooltip = null;

    private int $border_width = 0;

    private ?Color $border_color = null;

    /** The floor minSize() set; [0, 0] until set. */
    private array $minimum_size = [0, 0];

    /** The ceiling maxSize() set; [0, 0] (none) until set. */
    private array $maximum_size = [0, 0];

    /** What fill() set, horizontal then vertical; neither until set. */
    private array $fills = [false, false];

    /** What align() set, horizontal then vertical; FILL both ways until set. */
    private array $aligned = [Align::FILL, Align::FILL];

    /** Played when setVisible(true) shows the view; null shows it at once. */
    private ?TKTransition $appear = null;

    private float $appear_seconds = 0.0;

    /** Played backwards when setVisible(false) hides the view; null hides it at once. */
    private ?TKTransition $disappear = null;

    private float $disappear_seconds = 0.0;

    /**
     * The name is checked before any native exists; the parent takes the primitive in once
     * its native does. A primitive with no parent is a window's content container.
     *
     * @throws ToolkitException For an empty name, a name with a dot, or one a sibling holds.
     */
    public function __construct(
        protected readonly string $name,
        protected readonly ToolkitPoweredWindow $window,
        protected readonly ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
    ) {
        is_null($parent) ? self::guardName($name) : $parent->admit($name);

        $this->placement = $placement ?? Placement::next();
        $this->uuid = self::uuid4();

        $this->bootstrapNative();
        $parent?->adopt($this);
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Read from the tree each time: the names above this one, joined by dots.
     */
    public function path(): string
    {
        return is_null($this->parent) ? $this->name : "{$this->parent->path()}.{$this->name}";
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function window(): ToolkitPoweredWindow
    {
        return $this->window;
    }

    public function parent(): ?TKPrimitiveGroup
    {
        return $this->parent;
    }

    public function placement(): Placement
    {
        return $this->placement;
    }

    public function isRemoved(): bool
    {
        return $this->removed;
    }

    /**
     * Terminal. What is inside goes first, while this native still holds it. The primitive
     * is marked removed before its native goes, so whatever the toolkit signals while
     * destroying it (focus moving off, a last resize) finds a removed primitive.
     *
     * @throws ToolkitException When already removed.
     */
    public function remove(): void
    {
        $this->guardLive();

        $this->removeChildren();
        $this->removed = true;
        is_null($this->parent) ? $this->window->contentRemoved($this) : $this->parent->release($this);
        $this->releaseNative();
    }

    /**
     * Its own visibility: a visible primitive inside a hidden container still answers true.
     * Setting what it already is does nothing, so a transition is not played twice.
     */
    public function setVisible(bool $visible): static
    {
        $this->guardLive();

        if ($visible !== $this->visible) {
            $this->visible = $visible;
            $visible
                ? $this->applyVisible(true, $this->appear, $this->appear_seconds)
                : $this->applyVisible(false, $this->disappear, $this->disappear_seconds);
            $this->parent?->childVisibilityChanged($this);
        }

        return $this;
    }

    /**
     * Play the appear transition now, as when it is shown: for a primitive just added to a
     * window already on screen. Nothing when it is hidden, has no appear transition, or its
     * time is 0.
     *
     * @throws ToolkitException When removed.
     */
    public function playAppear(): static
    {
        $this->guardLive();

        if ($this->visible && ! is_null($this->appear) && $this->appear_seconds > 0.0) {
            $this->applyVisible(true, $this->appear, $this->appear_seconds);
        }

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function show(): static
    {
        return $this->setVisible(true);
    }

    public function hide(): static
    {
        return $this->setVisible(false);
    }

    /**
     * @throws ToolkitException When removed, or $opacity is outside 0.0..1.0.
     */
    public function setOpacity(float $opacity): static
    {
        $this->guardLive();

        if ($opacity < 0.0 || $opacity > 1.0) {
            throw new ToolkitException("Opacity is 0.0 to 1.0, got {$opacity} for '{$this->path()}'.");
        }

        $this->opacity = $opacity;
        $this->applyOpacity($opacity);

        return $this;
    }

    public function opacity(): float
    {
        return $this->opacity;
    }

    /**
     * @throws ToolkitException When removed, or $seconds is negative.
     */
    public function appear(?TKTransition $transition, float $seconds = TKTransition::DEFAULT_SECONDS): static
    {
        $this->guardLive();
        $this->guardSeconds($seconds);

        $this->appear = $transition;
        $this->appear_seconds = $seconds;

        return $this;
    }

    /**
     * @throws ToolkitException When removed, or $seconds is negative.
     */
    public function disappear(?TKTransition $transition, float $seconds = TKTransition::DEFAULT_SECONDS): static
    {
        $this->guardLive();
        $this->guardSeconds($seconds);

        $this->disappear = $transition;
        $this->disappear_seconds = $seconds;

        return $this;
    }

    /**
     * Its own enabled flag: an enabled primitive inside a disabled container still answers
     * true, and takes input again when the container is enabled.
     */
    public function setEnabled(bool $enabled): static
    {
        $this->guardLive();

        if ($enabled !== $this->enabled) {
            $this->enabled = $enabled;
            $this->applyEnabled($enabled);
        }

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): static
    {
        return $this->setEnabled(true);
    }

    public function disable(): static
    {
        return $this->setEnabled(false);
    }

    /**
     * Refused before the toolkit is asked, with the same reasons on every toolkit; the
     * toolkit's own answer is the last one.
     *
     * @throws ToolkitException When removed, not shown (hidden, or inside a hidden
     *         container), disabled (itself, or a container above it), or the native takes no focus.
     */
    public function focus(): static
    {
        $this->guardLive();

        if (! $this->isShown()) {
            throw new ToolkitException("'{$this->path()}' is not shown: it cannot take focus.");
        }
        if (! $this->takesInput()) {
            throw new ToolkitException("'{$this->path()}' is disabled: it cannot take focus.");
        }
        if (! $this->nativeFocus()) {
            throw new ToolkitException("'{$this->path()}' takes no focus.");
        }

        return $this;
    }

    /**
     * Context menus are not ported: ContextMenu has no definition yet, so there is nothing a
     * toolkit could build a menu from.
     *
     * @throws ToolkitException Always.
     */
    public function setContextMenu(array|ContextMenu|null $menu): static
    {
        throw new ToolkitException("Context menus are not ported yet: '{$this->path()}' cannot take one.");
    }

    public function contextMenu(): ?ContextMenu
    {
        return null;
    }

    /**
     * @throws ToolkitException Always: context menus are not ported yet.
     */
    public function popupMenu(array|ContextMenu $menu, float $x, float $y): static
    {
        throw new ToolkitException("Context menus are not ported yet: '{$this->path()}' cannot open one.");
    }

    public function watchSize(bool $on = true): static
    {
        $this->guardLive();
        if ($on !== $this->watching_size) {
            $this->watching_size = $on;
            $this->posted_size = null;
            $this->applyWatchSize($on);
        }

        return $this;
    }

    public function isWatchingSize(): bool
    {
        return $this->watching_size;
    }

    public function setBackground(?Color $color): static
    {
        $this->guardLive();
        $this->applyBackground($color);

        return $this;
    }

    /**
     * @throws ToolkitException When removed, or a radius is negative or cannot be read.
     */
    public function setCornerRadius(Corners|float|int|string|array $radius): static
    {
        $this->guardLive();

        try {
            $corners = Corners::of($radius);
        } catch (InvalidArgumentException $e) {
            throw new ToolkitException("Corner radius for '{$this->path()}': {$e->getMessage()}", previous: $e);
        }

        $this->corners = $corners;
        $this->applyCorners($corners);

        return $this;
    }

    public function cornerRadius(): float
    {
        return $this->corners()->largest();
    }

    public function corners(): Corners
    {
        return $this->corners ?? Corners::none();
    }

    /**
     * @throws ToolkitException When removed.
     */
    public function setClip(bool $clip = true): static
    {
        $this->guardLive();

        $this->clip = $clip;
        $this->applyClip($clip);

        return $this;
    }

    public function clips(): bool
    {
        return $this->clip;
    }

    /**
     * The container arranges the primitive first (some toolkits keep fill on the container's
     * layout, not on the widget), then the primitive applies its own part.
     */
    public function fill(bool $horizontal = true, bool $vertical = true): static
    {
        $this->guardLive();

        $this->fills = [$horizontal, $vertical];
        $this->parent?->childLayoutChanged($this);
        $this->applyFill($horizontal, $vertical);

        return $this;
    }

    /**
     * As minSize(): the container first, then the primitive.
     *
     * @throws ToolkitException When removed, or either is negative.
     */
    public function maxSize(int $width, int $height): static
    {
        $this->guardLive();

        if ($width < 0 || $height < 0) {
            throw new ToolkitException("A maximum size is 0 (none) or more each way, got {$width}x{$height} for '{$this->path()}'.");
        }

        $this->maximum_size = [$width, $height];
        $this->parent?->childLayoutChanged($this);
        $this->applyMaxSize($width, $height);

        return $this;
    }

    /**
     * As fill(): the container first, then the primitive.
     */
    public function align(Align $horizontal, Align $vertical = Align::FILL): static
    {
        $this->guardLive();

        $this->aligned = [$horizontal, $vertical];
        $this->parent?->childLayoutChanged($this);
        $this->applyAlign($horizontal, $vertical);

        return $this;
    }

    /**
     * @throws ToolkitException When removed, or either is negative.
     */
    public function minSize(int $width, int $height): static
    {
        $this->guardLive();

        if ($width < 0 || $height < 0) {
            throw new ToolkitException("A minimum size is 0 or more each way, got {$width}x{$height} for '{$this->path()}'.");
        }

        $this->minimum_size = [$width, $height];
        $this->parent?->childLayoutChanged($this);
        $this->applyMinSize($width, $height);

        return $this;
    }

    /**
     * As fill(): the container first (on some toolkits the space round a child is the
     * container's to keep), then the primitive.
     *
     * @throws ToolkitException When removed, or a side is negative or unreadable.
     */
    public function setMargin(Insets|int|string|array $margin): static
    {
        $this->guardLive();

        $this->margin = $this->insets($margin, 'margin');
        $this->parent?->childLayoutChanged($this);
        $this->applyMargin($this->margin);

        return $this;
    }

    public function margin(): Insets
    {
        return $this->margin ?? Insets::none();
    }

    public function setCursor(?Cursor $cursor): static
    {
        $this->guardLive();

        $this->cursor = $cursor;
        $this->applyCursor($this->cursorShown());

        return $this;
    }

    public function cursor(): ?Cursor
    {
        return $this->cursor;
    }

    public function setTooltip(?string $tooltip): static
    {
        $this->guardLive();

        $this->tooltip = $tooltip === '' ? null : $tooltip;
        $this->applyTooltip($this->tooltip);

        return $this;
    }

    public function tooltip(): ?string
    {
        return $this->tooltip;
    }

    /**
     * @throws ToolkitException When removed, or the width is negative.
     */
    public function setBorder(int $width, ?Color $color = null): static
    {
        $this->guardLive();

        if ($width < 0) {
            throw new ToolkitException("A border is 0 or more wide, got {$width} for '{$this->path()}'.");
        }

        $this->border_width = $width;
        $this->border_color = $color;
        $this->applyBorder($width, $color);

        return $this;
    }

    public function borderWidth(): int
    {
        return $this->border_width;
    }

    public function borderColor(): ?Color
    {
        return $this->border_color;
    }

    public function moveBefore(PrimitiveContract $sibling): static
    {
        return $this->moveBeside($sibling, 0);
    }

    public function moveAfter(PrimitiveContract $sibling): static
    {
        return $this->moveBeside($sibling, 1);
    }

    /**
     * @throws ToolkitException When removed, the parent is not a column or row, or the index is out of range.
     */
    public function moveTo(int $index): static
    {
        $this->guardLive();
        $this->orderedParent()->arrange($this, $index);

        return $this;
    }

    /**
     * The window's focus moved off this primitive, seen after a pump. A toolkit's own signal
     * for it cannot be relied on: an inactive window moves focus without one (Qt, GTK).
     *
     * @internal Called by the window.
     */
    public function focusMovedAway(): void {}

    /**
     * What fill() set, for a container that keeps fill on its own layout.
     *
     * @return array{bool, bool} horizontal, vertical
     */
    protected function fills(): array
    {
        return $this->fills;
    }

    /**
     * Whether it takes spare room along an axis: it fills, or something shown inside it does.
     */
    protected function expands(bool $horizontal): bool
    {
        return $this->fills[$horizontal ? 0 : 1];
    }

    /**
     * What align() set, for a container that keeps alignment on its own layout.
     *
     * @return array{Align, Align} horizontal, vertical
     */
    protected function aligned(): array
    {
        return $this->aligned;
    }

    /**
     * The floor minSize() set, for a container that sizes its children itself (a fixed sizes a
     * child to its frame, but never below this).
     *
     * @return array{int, int}
     */
    protected function minimumSize(): array
    {
        return $this->minimum_size;
    }

    /**
     * The ceiling maxSize() set, 0 for none that way, for a container that places its
     * children itself.
     *
     * @return array{int, int}
     */
    protected function maximumSize(): array
    {
        return $this->maximum_size;
    }

    /**
     * Shown: visible itself, and so is every container above it. The toolkit hides what is
     * inside a hidden container without changing the flags of what it hid, so this is read
     * from the tree.
     */
    protected function isShown(): bool
    {
        for ($at = $this; ! is_null($at); $at = $at->parent) {
            if (! $at->visible) {
                return false;
            }
        }

        return true;
    }

    /**
     * Takes input: enabled itself, and so is every container above it.
     */
    protected function takesInput(): bool
    {
        for ($at = $this; ! is_null($at); $at = $at->parent) {
            if (! $at->enabled) {
                return false;
            }
        }

        return true;
    }

    /**
     * The pointer shape to show: the one set, or the one this kind of primitive shows when none
     * is (a link-style button's pointing hand). Null is the toolkit's own.
     */
    protected function cursorShown(): ?Cursor
    {
        return $this->cursor;
    }

    /**
     * Read one to four values as CSS does.
     *
     * @throws ToolkitException When a side is negative or the value unreadable.
     */
    protected function insets(Insets|int|string|array $value, string $what): Insets
    {
        try {
            return Insets::of($value);
        } catch (InvalidArgumentException $e) {
            throw new ToolkitException("A {$what} for '{$this->path()}': {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * What is inside goes before this does. A container has something to do here.
     */
    protected function removeChildren(): void {}

    /**
     * @throws ToolkitException Once removed.
     */
    protected function guardLive(): void
    {
        if ($this->removed) {
            throw new ToolkitException("'{$this->path()}' is removed.");
        }
    }

    /**
     * A name is one segment of a dotted path.
     *
     * @throws ToolkitException For an empty name, or one with a dot.
     */
    protected static function guardName(string $name): void
    {
        if ($name === '') {
            throw new ToolkitException('A primitive needs a name.');
        }
        if (str_contains($name, '.')) {
            throw new ToolkitException("A primitive's name holds no dot (a dot separates the names of a path), got '{$name}'.");
        }
    }

    /**
     * @throws ToolkitException When removed, the parent is not a column or row, $sibling is
     *         not a sibling, or it is this primitive.
     */
    private function moveBeside(PrimitiveContract $sibling, int $offset): static
    {
        $this->guardLive();
        $parent = $this->orderedParent();

        if ($sibling === $this) {
            throw new ToolkitException("'{$this->path()}' cannot be moved next to itself.");
        }

        // Where the sibling sits once this primitive is out of the line.
        $others = array_values(array_filter($parent->children(), fn (PrimitiveContract $child): bool => $child !== $this));
        $at = array_search($sibling, $others, true);

        if ($at === false) {
            throw new ToolkitException("'{$sibling->path()}' is not a sibling of '{$this->path()}'.");
        }

        $parent->arrange($this, $at + $offset);

        return $this;
    }

    /**
     * @throws ToolkitException When this is a content container, or the parent keeps no order of its own.
     */
    private function orderedParent(): TKGroup
    {
        if ($this->parent instanceof TKGroup && $this->parent->keepsOrder()) {
            return $this->parent;
        }

        throw new ToolkitException("'{$this->path()}' is in no column, row or other container that keeps an order to move in.");
    }

    private function guardSeconds(float $seconds): void
    {
        if ($seconds < 0.0) {
            throw new ToolkitException("A transition runs 0.0 seconds or more, got {$seconds} for '{$this->path()}'.");
        }
    }

    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Build the native. Runs once, from the constructor, after the name is accepted and
     * before the parent takes the primitive in.
     */
    abstract protected function bootstrapNative(): void;

    /**
     * Destroy the native. Runs once, from remove(), after the parent let it go.
     */
    abstract protected function releaseNative(): void;

    /**
     * Show or hide the native. With a transition, play it over $seconds (backwards when
     * hiding, then hide); null changes it at once.
     */
    abstract protected function applyVisible(bool $visible, ?TKTransition $transition, float $seconds): void;

    /**
     * This primitive's own enabled flag. A container's reaches what is inside it the way the
     * toolkit carries it: GTK, Qt and Raven do it themselves; a toolkit that does not carries
     * it down in its own primitives.
     */
    abstract protected function applyEnabled(bool $enabled): void;

    abstract protected function applyOpacity(float $opacity): void;

    /**
     * Give the native the keyboard focus. False when it takes none.
     */
    abstract protected function nativeFocus(): bool;

    /**
     * Null restores the toolkit's own background.
     */
    abstract protected function applyBackground(?Color $color): void;

    abstract protected function applyCornerRadius(float $radius): void;

    abstract protected function applyFill(bool $horizontal, bool $vertical): void;

    abstract protected function applyAlign(Align $horizontal, Align $vertical): void;

    abstract protected function applyMinSize(int $width, int $height): void;

    /**
     * Start or stop hearing the native's resizes, with the toolkit's own callback where it
     * has one; where it has none, the adapter compares the size after each pump. Either way
     * it calls sizeChanged().
     */
    abstract protected function applyWatchSize(bool $on): void;

    /**
     * The native may have changed size: ViewResized, the latest per pump, while watching and
     * when size() differs from the size last posted. A size with no width or no height (not
     * laid out yet, or hidden) is not posted.
     */
    protected function sizeChanged(): void
    {
        if (! $this->watching_size || $this->removed) {
            return;
        }

        $size = $this->size();
        if ($size === $this->posted_size || in_array(0, $size, true)) {
            return;
        }

        $this->posted_size = $size;
        $this->window()->postLatest('view.resized.'.$this->uuid(), new ViewResized($this->window()->name(), $this->path(), $this->uuid(), $size[0], $size[1]));
    }

    /**
     * 0 is no ceiling that way. Does nothing until an adapter overrides it: a toolkit with no
     * native ceiling lets the view grow as before.
     */
    protected function applyMaxSize(int $width, int $height): void {}

    /**
     * Space outside the native. Nothing until the adapter can keep it natively; a toolkit
     * whose containers keep the space round each child does it in childLayoutChanged(),
     * reading margin(), and may leave this empty.
     */
    protected function applyMargin(Insets $margin): void {}

    /**
     * Null is the toolkit's own pointer.
     */
    protected function applyCursor(?Cursor $cursor): void {}

    /**
     * Null shows none.
     */
    protected function applyTooltip(?string $tooltip): void {}

    /**
     * Width 0 takes the line away; a null colour is the toolkit's own line colour.
     */
    protected function applyBorder(int $width, ?Color $color): void {}

    /**
     * Each corner its own radius. A toolkit that rounds corners apart overrides this; the
     * start gives applyCornerRadius() the one radius when they are all the same, and square
     * corners when they differ, since rounding every corner would be wrong.
     */
    protected function applyCorners(Corners $corners): void
    {
        $this->applyCornerRadius($corners->isUniform() ? $corners->topLeft : 0.0);
    }

    /**
     * True cuts what is inside at the edge and its corner radius; false shows it whole.
     */
    protected function applyClip(bool $clip): void {}
}
