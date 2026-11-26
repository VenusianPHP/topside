<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\ButtonRole;
use Surface\Contracts\Toolkits\UI\ButtonStyle;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\NutsAndBolts\Color;

/**
 * A push button. Posts ButtonClicked.
 *
 * Each setting is the toolkit's own: a toolkit with no native way to show one keeps the
 * button as it was, and the getter still answers what was set.
 */
interface TKButton extends TKPrimitive
{
    /**
     * @return string
     */
    public function label(): string;

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): static;

    /**
     * DEFAULT until set. LINK shows a pointing hand unless setCursor() chose another.
     * @param ButtonStyle $style
     * @return $this
     */
    public function setStyle(ButtonStyle $style): static;

    /**
     * @return ButtonStyle
     */
    public function style(): ButtonStyle;

    /**
     * @param FontSpec $font
     * @return $this
     */
    public function setFont(FontSpec $font): static;

    /**
     * @param Color|null $color null restores the toolkit's own (the link colour, in LINK style)
     * @return $this
     */
    public function setTextColor(?Color $color): static;

    /**
     * The label's place inside the button; centre until set.
     * @param TextAlignment $alignment
     * @return $this
     */
    public function setAlignment(TextAlignment $alignment): static;

    /**
     * An image beside the label (setIconPosition() says which side): a file, absolute or under resources/. Null removes it.
     * @param string|null $file
     * @return $this
     */
    public function setIcon(?string $file): static;

    /**
     * @return string|null
     */
    public function icon(): ?string;

    /**
     * A symbol beside the label, by the name the toolkit knows it by: an SF Symbol on AppKit,
     * a theme icon on GTK and Qt, one of Raven's own icons. An icon file wins over it.
     * @param string|null $name
     * @return $this
     */
    public function setSymbol(?string $name): static;

    /**
     * @return string|null
     */
    public function symbol(): ?string;

    /**
     * Which side of the label the icon or symbol sits on; LEADING until set.
     * @param IconPosition $position
     * @return $this
     */
    public function setIconPosition(IconPosition $position): static;

    /**
     * @return IconPosition
     */
    public function iconPosition(): IconPosition;

    /**
     * DEFAULT: Return clicks it. CANCEL: Esc clicks it. Null removes the role. Where the
     * toolkit gives a button no such role, the keys do nothing.
     * @param ButtonRole|null $role
     * @return $this
     */
    public function setRole(?ButtonRole $role): static;

    /**
     * @return ButtonRole|null
     */
    public function role(): ?ButtonRole;

    /**
     * Space between the button's edge and its label and icon, each side its own (an int is
     * every side; a string or list is one to four values in CSS order). None set: the
     * toolkit's own button padding. Where a toolkit cannot inset a side on its own, it
     * shows what it can and says so in its adapter.
     * @param Insets|int|string|list<int> $padding
     * @return $this
     */
    public function setPadding(Insets|int|string|array $padding): static;

    /**
     * @return Insets
     */
    public function padding(): Insets;
}
