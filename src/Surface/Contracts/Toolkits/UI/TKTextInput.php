<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\NutsAndBolts\Color;

/**
 * One line of editable text. Posts TextChanged as the user types and TextSubmitted on return.
 */
interface TKTextInput extends TKPrimitive
{
    /**
     * @return string
     */
    public function value(): string;

    /**
     * Posts nothing: the app changed it, it knows.
     * @param string $value
     * @return $this
     */
    public function setValue(string $value): static;

    /**
     * @return string|null
     */
    public function placeholder(): ?string;

    /**
     * @param string|null $placeholder
     * @return $this
     */
    public function setPlaceholder(?string $placeholder): static;

    /**
     * Whether the entry masks what is typed; fixed at creation.
     * @return bool
     */
    public function isSecret(): bool;

    /**
     * @param FontSpec $font
     * @return $this
     */
    public function setFont(FontSpec $font): static;

    /**
     * @param Color|null $color null restores the toolkit's own
     * @return $this
     */
    public function setTextColor(?Color $color): static;

    /** The text's place inside the view; left until set. */
    public function setAlignment(TextAlignment $alignment): static;

    /**
     * Built as the toolkit's search field (its search glyph, its clear button) where it has one.
     * @return bool
     */
    public function isSearch(): bool;

    /**
     * No frame and no field background of its own. False until set.
     * @param bool $borderless
     * @return $this
     */
    public function setBorderless(bool $borderless = true): static;

    /**
     * @return bool
     */
    public function isBorderless(): bool;

    /**
     * The text can be selected and copied, not changed. False until set.
     * @param bool $readOnly
     * @return $this
     */
    public function setReadOnly(bool $readOnly = true): static;

    /**
     * @return bool
     */
    public function isReadOnly(): bool;

    /**
     * The most characters the user can type; null is no limit.
     * @param int|null $length
     * @return $this
     * @throws \RuntimeException When the length is below 1.
     */
    public function setMaxLength(?int $length): static;

    /**
     * @return int|null
     */
    public function maxLength(): ?int;
}
