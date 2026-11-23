<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\ButtonRole;
use Surface\Contracts\Toolkits\UI\HasButtonImage;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\NutsAndBolts\Color;

/**
 * A push button. Posts ButtonClicked.
 *
 * For now a button is its label and its click. Its image and bordered look (HasButtonImage)
 * and what is commented out below come back as each is needed.
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

    // Not needed yet: uncomment as each lands.

    // /**
    //  * @param FontSpec $font
    //  * @return $this
    //  */
    // public function setFont(FontSpec $font): static;

    // /**
    //  * @param Color|null $color null restores the toolkit's own
    //  * @return $this
    //  */
    // public function setTextColor(?Color $color): static;

    // /** The text's place inside the view; centre until set. */
    // public function setAlignment(TextAlignment $alignment): static;

    // /**
    //  * Give the button a role, or remove its role. The window's previous holder of the role loses
    //  * it; a button holds one role at most. Posts nothing.
    //  *
    //  * @param ButtonRole|null $role
    //  * @return $this
    //  */
    // public function setRole(?ButtonRole $role): static;

    // /**
    //  * @return ButtonRole|null
    //  */
    // public function role(): ?ButtonRole;
}
