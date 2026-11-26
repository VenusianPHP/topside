<?php

namespace Surface\Contracts\Toolkits\UI;

/**
 * How a push button draws:
 *
 * DEFAULT    the toolkit's own button, with its frame.
 * BORDERLESS no frame and no fill of its own: the label (and icon) alone, still a button.
 * LINK       borderless, its label in the toolkit's link colour, a pointing hand over it.
 * HELP       the toolkit's help button (AppKit's round "?", which draws no label); a toolkit
 *            with none draws DEFAULT, its label shown.
 */
enum ButtonStyle: string
{
    case DEFAULT = 'default';
    case BORDERLESS = 'borderless';
    case LINK = 'link';
    case HELP = 'help';

    /** Whether the toolkit draws the button's own frame: DEFAULT and HELP. */
    public function framed(): bool
    {
        return $this === self::DEFAULT || $this === self::HELP;
    }
}
