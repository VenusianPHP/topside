<?php

namespace Surface\Toolkits\UI;

use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\Cursor;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\UI\ButtonRole;
use Surface\Contracts\Toolkits\UI\ButtonStyle;
use Surface\Contracts\Toolkits\UI\IconPosition;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\Mail\Views\ButtonClicked;
use Surface\Contracts\Toolkits\UI\TKButton as PrimitiveContract;

/**
 * A push button: its label, its click, and how it looks.
 *
 * The toolkit's button tells this one it was clicked, by calling clicked() from the
 * native's own click signal; the mail is made here, so it is the same on every toolkit.
 *
 * Only applyLabel() is required of an adapter. The others do nothing until it overrides them:
 * a toolkit with no native way to show a setting leaves the button as it was.
 */
abstract class TKButton extends TKPrimitive implements PrimitiveContract
{
    private ButtonStyle $style = ButtonStyle::DEFAULT;

    private ?ButtonRole $role = null;

    private ?string $icon = null;

    private ?string $symbol = null;

    private IconPosition $icon_position = IconPosition::LEADING;

    private ?Insets $padding = null;

    /**
     * The label is known before the native is built, so it is built with it.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private string $label = '',
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->guardLive();

        $this->label = $label;
        $this->applyLabel($label);

        return $this;
    }

    /**
     * The style first, then the pointer it brings: a link shows a pointing hand unless a cursor
     * was set.
     */
    public function setStyle(ButtonStyle $style): static
    {
        $this->guardLive();

        $this->style = $style;
        $this->applyStyle($style);
        $this->applyCursor($this->cursorShown());

        return $this;
    }

    public function style(): ButtonStyle
    {
        return $this->style;
    }

    public function setFont(FontSpec $font): static
    {
        $this->guardLive();
        $this->applyFont($font);

        return $this;
    }

    public function setTextColor(?Color $color): static
    {
        $this->guardLive();
        $this->applyTextColor($color);

        return $this;
    }

    public function setAlignment(TextAlignment $alignment): static
    {
        $this->guardLive();
        $this->applyAlignment($alignment);

        return $this;
    }

    public function setIcon(?string $file): static
    {
        $this->guardLive();

        $this->icon = $file === '' ? null : $file;
        $this->applyIcon($this->icon, $this->symbol);

        return $this;
    }

    public function icon(): ?string
    {
        return $this->icon;
    }

    public function setSymbol(?string $name): static
    {
        $this->guardLive();

        $this->symbol = $name === '' ? null : $name;
        $this->applyIcon($this->icon, $this->symbol);

        return $this;
    }

    public function symbol(): ?string
    {
        return $this->symbol;
    }

    public function setIconPosition(IconPosition $position): static
    {
        $this->guardLive();

        $this->icon_position = $position;
        $this->applyIconPosition($position);

        return $this;
    }

    public function iconPosition(): IconPosition
    {
        return $this->icon_position;
    }

    public function setRole(?ButtonRole $role): static
    {
        $this->guardLive();

        $this->role = $role;
        $this->applyRole($role);

        return $this;
    }

    public function role(): ?ButtonRole
    {
        return $this->role;
    }

    /**
     * @throws ToolkitException When removed, or the padding is not 1-4 points at least 0.
     */
    public function setPadding(Insets|int|string|array $padding): static
    {
        $this->guardLive();

        $this->padding = $this->insets($padding, 'padding');
        $this->applyPadding($this->padding);

        return $this;
    }

    public function padding(): Insets
    {
        return $this->padding ?? Insets::none();
    }

    /**
     * The native was clicked: post ButtonClicked. Nothing while the button or a container
     * above it is disabled (a toolkit blocks the user's clicks there, but not every toolkit
     * blocks a click made from code), and nothing from a button that is going (removed, its
     * native not yet destroyed).
     */
    protected function clicked(): void
    {
        if ($this->isRemoved() || ! $this->takesInput()) {
            return;
        }

        $this->window()->post(new ButtonClicked($this->window()->name(), $this->path(), $this->uuid()));
    }

    protected function cursorShown(): ?Cursor
    {
        return $this->cursor() ?? ($this->style === ButtonStyle::LINK ? Cursor::POINTER : null);
    }

    abstract protected function applyLabel(string $label): void;

    /**
     * The padding changed; none (Insets::none()) gives the toolkit's own button padding back.
     */
    abstract protected function applyPadding(Insets $padding): void;

    /**
     * DEFAULT restores the toolkit's own button. LINK is borderless, in the link colour unless
     * setTextColor() chose one; its pointer comes through applyCursor().
     */
    protected function applyStyle(ButtonStyle $style): void {}

    protected function applyFont(FontSpec $font): void {}

    /**
     * Null restores the toolkit's own: the link colour in LINK style.
     */
    protected function applyTextColor(?Color $color): void {}

    protected function applyAlignment(TextAlignment $alignment): void {}

    /**
     * Both as set: show $file when there is one, else the toolkit's symbol $symbol, else none.
     */
    protected function applyIcon(?string $file, ?string $symbol): void {}

    /**
     * Where the toolkit puts a button's icon on one side only, it stays there.
     */
    protected function applyIconPosition(IconPosition $position): void {}

    protected function applyRole(?ButtonRole $role): void {}
}
